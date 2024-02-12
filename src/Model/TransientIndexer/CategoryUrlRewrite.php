<?php

namespace Infrangible\IndexPartial\Model\TransientIndexer;

use Exception;
use Infrangible\Core\Helper\Attribute;
use Infrangible\Core\Helper\Database;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Model\Category;
use Magento\CatalogUrlRewrite\Model\CategoryUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\CategoryUrlRewriteGenerator;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\UrlRewriteBunchReplacer;
use Magento\CatalogUrlRewrite\Observer\UrlRewriteHandler;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\ActionInterface;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Psr\Log\LoggerInterface;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class CategoryUrlRewrite
    implements ActionInterface
{
    /** @var Stores */
    protected $storeHelper;

    /** @var Database */
    protected $databaseHelper;

    /** @var Variables */
    protected $variableHelper;

    /** @var Attribute */
    protected $attributeHelper;

    /** @var \Infrangible\Core\Helper\Category */
    protected $categoryHelper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var CategoryUrlRewriteGenerator */
    protected $categoryUrlRewriteGenerator;

    /** @var UrlRewriteHandler */
    protected $urlRewriteHandler;

    /** @var UrlRewriteBunchReplacer */
    protected $urlRewriteBunchReplacer;

    /** @var DatabaseMapPool */
    protected $databaseMapPool;

    /** @var CategoryUrlPathGenerator */
    protected $categoryUrlPathGenerator;

    /** @var UrlPersistInterface */
    protected $urlPersist;

    /** @var bool */
    private $executeList = false;

    /** @var array */
    private $resetDatabaseMapPoolCategoryIds = [];

    /** @var int[] */
    private $rootCategoryIds = [];

    /** @var array */
    private $categoryAttributeUpdates = [];

    /**
     * @param Stores                            $storeHelper
     * @param Database                          $databaseHelper
     * @param Variables                         $variableHelper
     * @param Attribute                         $attributeHelper
     * @param \Infrangible\Core\Helper\Category $categoryHelper
     * @param LoggerInterface                   $logging
     * @param CategoryUrlRewriteGenerator       $categoryUrlRewriteGenerator
     * @param UrlRewriteHandler                 $urlRewriteHandler
     * @param UrlRewriteBunchReplacer           $urlRewriteBunchReplacer
     * @param DatabaseMapPool                   $databaseMapPool
     * @param CategoryUrlPathGenerator          $categoryUrlPathGenerator
     * @param UrlPersistInterface               $urlPersist
     */
    public function __construct(
        Stores $storeHelper,
        Database $databaseHelper,
        Variables $variableHelper,
        Attribute $attributeHelper,
        \Infrangible\Core\Helper\Category $categoryHelper,
        LoggerInterface $logging,
        CategoryUrlRewriteGenerator $categoryUrlRewriteGenerator,
        UrlRewriteHandler $urlRewriteHandler,
        UrlRewriteBunchReplacer $urlRewriteBunchReplacer,
        DatabaseMapPool $databaseMapPool,
        CategoryUrlPathGenerator $categoryUrlPathGenerator,
        UrlPersistInterface $urlPersist)
    {
        $this->storeHelper = $storeHelper;
        $this->databaseHelper = $databaseHelper;
        $this->variableHelper = $variableHelper;
        $this->attributeHelper = $attributeHelper;
        $this->categoryHelper = $categoryHelper;

        $this->logging = $logging;
        $this->categoryUrlRewriteGenerator = $categoryUrlRewriteGenerator;
        $this->urlRewriteHandler = $urlRewriteHandler;
        $this->urlRewriteBunchReplacer = $urlRewriteBunchReplacer;
        $this->databaseMapPool = $databaseMapPool;
        $this->categoryUrlPathGenerator = $categoryUrlPathGenerator;
        $this->urlPersist = $urlPersist;
    }

    /**
     * Execute full indexation
     *
     * @return void
     * @throws Exception
     */
    public function executeFull()
    {
        $categoryCollection = $this->categoryHelper->getCategoryCollection();

        $ids = $categoryCollection->getAllIds();

        $this->executeList = true;

        foreach ($ids as $id) {
            $this->indexCategory($id);
        }

        $this->executeList = false;

        $this->updateCategoryAttributes($this->categoryAttributeUpdates);
        $this->resetDatabaseMapPool($this->resetDatabaseMapPoolCategoryIds);
    }

    /**
     * Execute partial indexation by ID list
     *
     * @param int[] $ids
     *
     * @return void
     * @throws Exception
     */
    public function executeList(array $ids)
    {
        $ids = array_unique($ids);

        $this->executeList = true;

        foreach ($ids as $id) {
            $this->indexCategory($id);
        }

        $this->executeList = false;

        $this->updateCategoryAttributes($this->categoryAttributeUpdates);
        $this->resetDatabaseMapPool($this->resetDatabaseMapPoolCategoryIds);
    }

    /**
     * Execute partial indexation by ID
     *
     * @param int $id
     *
     * @return void
     * @throws Exception
     */
    public function executeRow($id)
    {
        $this->indexCategory($id);
    }

    /**
     * Execute partial indexation by ID
     *
     * @param int $categoryId
     *
     * @return void
     * @throws Exception
     */
    private function indexCategory(int $categoryId)
    {
        $category = $this->categoryHelper->loadCategory($categoryId);

        foreach ($category->getStoreIds() as $storeId) {
            if ((int)$storeId === 0) {
                continue;
            }

            $category = $this->categoryHelper->loadCategory($categoryId, $storeId);

            $rootCategoryId = $this->getRootCategoryId($storeId);

            if ((int)$category->getId() !== $rootCategoryId) {
                $urlKey = $category->getUrlKey();
                $urlPath = $category->getDataUsingMethod('url_path');

                if ($this->variableHelper->isEmpty($urlKey)) {
                    $createdUrlKey = $this->categoryUrlPathGenerator->getUrlKey($category);

                    $category->setUrlKey($createdUrlKey);

                    if ($urlKey !== $createdUrlKey) {
                        $this->updateCategoryAttribute($categoryId, 'url_key', $storeId, $createdUrlKey);
                    }
                }

                $createdUrlPath = $this->generateCategoryUrlRewrites($category, $rootCategoryId);

                if ($urlPath !== $createdUrlPath) {
                    $this->updateCategoryAttribute($categoryId, 'url_path', $storeId, $createdUrlPath);
                }
            }
        }

        $this->resetDatabaseMapPool([$category->getEntityId()]);
    }

    /**
     * @param array $categoryIds
     */
    private function resetDatabaseMapPool(array $categoryIds)
    {
        if ($this->executeList) {
            foreach ($categoryIds as $categoryId) {
                $this->resetDatabaseMapPoolCategoryIds[] = $categoryId;
            }
        } else {
            $categoryIds = array_unique($categoryIds);

            $this->logging->debug(sprintf('Resetting database map pool of categories with ids: %s',
                implode(',', $categoryIds)));

            foreach ($categoryIds as $categoryId) {
                foreach ([
                    DataCategoryUrlRewriteDatabaseMap::class,
                    DataProductUrlRewriteDatabaseMap::class
                ] as $className) {
                    $this->databaseMapPool->resetMap($className, $categoryId);
                }
            }
        }
    }

    /**
     * @param int $storeId
     *
     * @return int
     */
    private function getRootCategoryId(int $storeId): int
    {
        if ( ! array_key_exists($storeId, $this->rootCategoryIds)) {
            try {
                $store = $this->storeHelper->getStore($storeId);

                $this->rootCategoryIds[ $storeId ] = $store->getRootCategoryId();
            } catch (NoSuchEntityException $exception) {
                $this->rootCategoryIds[ $storeId ] = 0;
            }
        }

        return $this->rootCategoryIds[ $storeId ];
    }

    /**
     * @param int    $categoryId
     * @param string $attributeCode
     * @param int    $storeId
     * @param string $value
     *
     * @throws Exception
     */
    private function updateCategoryAttribute(int $categoryId, string $attributeCode, int $storeId, string $value)
    {
        $this->logging->debug(sprintf('Updating attribute with code: %s of category with id: %d in store with id: %d with value: %s',
            $attributeCode, $categoryId, $storeId, $value));

        $dbAdapter = $this->databaseHelper->getDefaultConnection();

        $attribute = $this->attributeHelper->getAttribute(Category::ENTITY, $attributeCode);

        $categoryAttributeUpdates = [];

        if ($storeId > 0) {
            $defaultValueQuery = $dbAdapter->select();

            $defaultValueQuery->from($attribute->getBackendTable());
            $defaultValueQuery->where('attribute_id = ?', $attribute->getId());
            $defaultValueQuery->where('store_id = ?', 0);
            $defaultValueQuery->where('entity_id = ?', $categoryId);

            $defaultValueQueryResult = $dbAdapter->fetchRow($defaultValueQuery);

            if ($this->variableHelper->isEmpty($defaultValueQueryResult)) {
                $categoryAttributeUpdates[ $attribute->getBackendTable() ][] = [
                    'attribute_id' => $attribute->getId(),
                    'store_id'     => 0,
                    'entity_id'    => $categoryId,
                    'value'        => null
                ];
            }
        }

        $categoryAttributeUpdates[ $attribute->getBackendTable() ][] = [
            'attribute_id' => $attribute->getId(),
            'store_id'     => $storeId,
            'entity_id'    => $categoryId,
            'value'        => $value
        ];

        if ($this->executeList) {
            foreach ($categoryAttributeUpdates as $tableName => $tableCategoryAttributeUpdate) {
                $this->categoryAttributeUpdates[ $tableName ][] = $tableCategoryAttributeUpdate;
            }
        } else {
            $this->updateCategoryAttributes($categoryAttributeUpdates);
        }
    }

    /**
     * @param array[] $categoryAttributeUpdates
     */
    private function updateCategoryAttributes(array $categoryAttributeUpdates)
    {
        $dbAdapter = $this->databaseHelper->getDefaultConnection();

        foreach ($categoryAttributeUpdates as $tableName => $tableCategoryAttributeUpdates) {
            foreach ($tableCategoryAttributeUpdates as $tableCategoryAttributeUpdate) {
                $dbAdapter->insertOnDuplicate($tableName, $tableCategoryAttributeUpdate, ['value']);
            }
        }
    }

    /**
     * @param Category $category
     * @param int      $rootCategoryId
     * @param int      $retry
     *
     * @return string|null
     * @throws Exception
     */
    private function generateCategoryUrlRewrites(Category $category, int $rootCategoryId, int $retry = 0): ?string
    {
        $this->logging->debug(sprintf('Generate url rewrite for category with id: %d in store with id: %d',
            $category->getId(), $category->getStoreId()));

        if ($retry > 0) {
            $category->unsetData('url_path');
        }

        $urlPath = $this->categoryUrlPathGenerator->getUrlPath($category);

        $category->setDataUsingMethod('url_path', $retry > 0 ? sprintf('%s-%d', $urlPath, $retry) : $urlPath);

        try {
            $categoryUrlRewrites = $this->categoryUrlRewriteGenerator->generate($category, true, $rootCategoryId);

            foreach (array_chunk($categoryUrlRewrites, 10000) as $urlsBunch) {
                $this->urlPersist->replace($urlsBunch);
            }

            $productUrls = $this->urlRewriteHandler->generateProductUrlRewrites($category);

            foreach (array_chunk($productUrls, 10000) as $urlsBunch) {
                $this->urlPersist->replace($urlsBunch);
            }
        } catch (UrlAlreadyExistsException $exception) {
            $urlPath = $category->getDataUsingMethod('url_path');

            if ( ! $this->variableHelper->isEmpty($urlPath) && $retry < 10) {
                return $this->generateCategoryUrlRewrites($category, $rootCategoryId, $retry + 1);
            } else {
                return null;
            }
        }

        return $category->getDataUsingMethod('url_path');
    }
}
