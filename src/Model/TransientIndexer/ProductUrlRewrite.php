<?php

namespace Infrangible\IndexPartial\Model\TransientIndexer;

use Exception;
use Infrangible\Core\Helper\Attribute;
use Infrangible\Core\Helper\Database;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\ProductFactory;
use Magento\CatalogUrlRewrite\Model\Map\DatabaseMapPool;
use Magento\CatalogUrlRewrite\Model\Map\DataCategoryUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\Map\DataProductUrlRewriteDatabaseMap;
use Magento\CatalogUrlRewrite\Model\ProductUrlPathGenerator;
use Magento\CatalogUrlRewrite\Model\ProductUrlRewriteGenerator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Indexer\ActionInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\Exception\UrlAlreadyExistsException;
use Magento\UrlRewrite\Model\UrlPersistInterface;
use Psr\Log\LoggerInterface;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ProductUrlRewrite
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

    /** @var \Infrangible\Core\Helper\Product */
    protected $productHelper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var ProductUrlRewriteGenerator */
    protected $productUrlRewriteGenerator;

    /** @var ProductUrlPathGenerator */
    protected $productUrlPathGenerator;

    /** @var UrlPersistInterface */
    protected $urlPersist;

    /** @var DatabaseMapPool */
    protected $databaseMapPool;

    /** @var ProductFactory */
    protected $productResourceFactory;

    /** @var bool */
    private $executeList = false;

    /** @var array */
    private $productAttributeUpdates = [];

    /** @var array */
    private $resetDatabaseMapPoolCategoryIds = [];

    /**
     * @param Stores                           $storeHelper
     * @param Database                         $databaseHelper
     * @param Variables                        $variableHelper
     * @param Attribute                        $attributeHelper
     * @param \Infrangible\Core\Helper\Product $productHelper
     * @param LoggerInterface                  $logging
     * @param ProductFactory                   $productResourceFactory
     * @param ProductUrlRewriteGenerator       $productUrlRewriteGenerator
     * @param ProductUrlPathGenerator          $productUrlPathGenerator
     * @param UrlPersistInterface              $urlPersist
     * @param DatabaseMapPool                  $databaseMapPool
     */
    public function __construct(
        Stores $storeHelper,
        Database $databaseHelper,
        Variables $variableHelper,
        Attribute $attributeHelper,
        \Infrangible\Core\Helper\Product $productHelper,
        LoggerInterface $logging,
        ProductFactory $productResourceFactory,
        ProductUrlRewriteGenerator $productUrlRewriteGenerator,
        ProductUrlPathGenerator $productUrlPathGenerator,
        UrlPersistInterface $urlPersist,
        DatabaseMapPool $databaseMapPool)
    {
        $this->storeHelper = $storeHelper;
        $this->databaseHelper = $databaseHelper;
        $this->variableHelper = $variableHelper;
        $this->attributeHelper = $attributeHelper;
        $this->productHelper = $productHelper;

        $this->logging = $logging;
        $this->productResourceFactory = $productResourceFactory;
        $this->productUrlRewriteGenerator = $productUrlRewriteGenerator;
        $this->productUrlPathGenerator = $productUrlPathGenerator;
        $this->urlPersist = $urlPersist;
        $this->databaseMapPool = $databaseMapPool;
    }

    /**
     * Execute full indexation
     *
     * @return void
     * @throws LocalizedException
     */
    public function executeFull()
    {
        $this->executeList = true;

        $productCollection = $this->productHelper->getProductCollection();

        foreach ($productCollection->getAllIds() as $id) {
            $this->indexProduct($id);
        }

        $this->executeList = false;

        $this->updateProductAttributes($this->productAttributeUpdates);
        $this->resetDatabaseMapPool($this->resetDatabaseMapPoolCategoryIds);
    }

    /**
     * Execute partial indexation by ID list
     *
     * @param int[] $ids
     *
     * @return void
     * @throws LocalizedException
     */
    public function executeList(array $ids)
    {
        $ids = array_unique($ids);

        $this->executeList = true;

        foreach ($ids as $id) {
            $this->indexProduct($id);
        }

        $this->executeList = false;

        $this->updateProductAttributes($this->productAttributeUpdates);
        $this->resetDatabaseMapPool($this->resetDatabaseMapPoolCategoryIds);
    }

    /**
     * Execute partial indexation by ID
     *
     * @param int|string $id
     *
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    public function executeRow($id)
    {
        $this->indexProduct($id);
    }

    /**
     * @param int|string $productId
     *
     * @return void
     * @throws LocalizedException
     * @throws Exception
     */
    private function indexProduct($productId)
    {
        $productWebsiteIds = $this->productResourceFactory->create()->getWebsiteIdsByProductIds([$productId]);

        if (array_key_exists($productId, $productWebsiteIds)) {
            foreach ($productWebsiteIds[ $productId ] as $websiteId) {
                $website = $this->storeHelper->getWebsite($websiteId);

                /** @var Store $store */
                foreach ($website->getStores() as $store) {
                    $this->indexStoreProduct($productId, $store->getId());
                }
            }
        }
    }

    /**
     * @param int $productId
     * @param int $storeId
     *
     * @throws Exception
     */
    private function indexStoreProduct(int $productId, int $storeId)
    {
        $product = $this->productHelper->loadProduct($productId, $storeId);

        if ($product->isVisibleInSiteVisibility()) {
            $urlKey = $product->getUrlKey();
            $urlPath = $product->getDataUsingMethod('url_path');

            if ($this->variableHelper->isEmpty($urlKey)) {
                $createdUrlKey = $this->productUrlPathGenerator->getUrlKey($product);

                $product->setUrlKey($createdUrlKey);

                if ($urlKey !== $createdUrlKey) {
                    $this->updateProductAttribute($productId, 'url_key', $storeId, $createdUrlKey);
                }
            }

            $createdUrlPath = $this->generateProductUrlRewrites($product);

            if ($urlPath !== $createdUrlPath) {
                $this->updateProductAttribute($productId, 'url_path', $storeId, $createdUrlPath);
            }
        }

        $this->resetDatabaseMapPool($product->getCategoryIds());
    }

    /**
     * @param int    $productId
     * @param string $attributeCode
     * @param int    $storeId
     * @param string $value
     *
     * @throws Exception
     */
    private function updateProductAttribute(int $productId, string $attributeCode, int $storeId, string $value)
    {
        $this->logging->debug(sprintf('Updating attribute with code: %s of product with id: %d in store with id: %d with value: %s',
            $attributeCode, $productId, $storeId, $value));

        $dbAdapter = $this->databaseHelper->getDefaultConnection();

        $attribute = $this->attributeHelper->getAttribute(Product::ENTITY, $attributeCode);

        $productAttributeUpdates = [];

        if ($storeId > 0) {
            $defaultValueQuery = $dbAdapter->select();

            $defaultValueQuery->from($attribute->getBackendTable());
            $defaultValueQuery->where('attribute_id = ?', $attribute->getId());
            $defaultValueQuery->where('store_id = ?', 0);
            $defaultValueQuery->where('entity_id = ?', $productId);

            $defaultValueQueryResult = $dbAdapter->fetchRow($defaultValueQuery);

            if ($this->variableHelper->isEmpty($defaultValueQueryResult)) {
                $productAttributeUpdates[ $attribute->getBackendTable() ][] = [
                    'attribute_id' => $attribute->getId(),
                    'store_id'     => 0,
                    'entity_id'    => $productId,
                    'value'        => null
                ];
            }
        }

        $productAttributeUpdates[ $attribute->getBackendTable() ][] = [
            'attribute_id' => $attribute->getId(),
            'store_id'     => $storeId,
            'entity_id'    => $productId,
            'value'        => $value
        ];

        if ($this->executeList) {
            foreach ($productAttributeUpdates as $tableName => $tableProductAttributeUpdate) {
                $this->productAttributeUpdates[ $tableName ][] = $tableProductAttributeUpdate;
            }
        } else {
            $this->updateProductAttributes($productAttributeUpdates);
        }
    }

    /**
     * @param array[] $productAttributeUpdates
     */
    private function updateProductAttributes(array $productAttributeUpdates)
    {
        $dbAdapter = $this->databaseHelper->getDefaultConnection();

        foreach ($productAttributeUpdates as $tableName => $tableProductAttributeUpdates) {
            foreach ($tableProductAttributeUpdates as $tableProductAttributeUpdate) {
                $dbAdapter->insertOnDuplicate($tableName, $tableProductAttributeUpdate, ['value']);
            }
        }
    }

    /**
     * @param Product $product
     * @param int     $retry
     *
     * @return string|null
     * @throws Exception
     */
    private function generateProductUrlRewrites(Product $product, int $retry = 0): ?string
    {
        $this->logging->debug(sprintf('Generate url rewrite for product with id: %d in store with id: %d',
            $product->getId(), $product->getStoreId()));

        if ($retry > 0) {
            $product->unsetData('url_path');
        }

        $urlPath = $this->productUrlPathGenerator->getUrlPath($product);

        $product->setDataUsingMethod('url_path', $retry > 0 ? sprintf('%s-%d', $urlPath, $retry) : $urlPath);

        try {
            $this->urlPersist->replace($this->productUrlRewriteGenerator->generate($product));
        } catch (UrlAlreadyExistsException $exception) {
            $urlPath = $product->getDataUsingMethod('url_path');

            if ( ! $this->variableHelper->isEmpty($urlPath) && $retry < 10) {
                return $this->generateProductUrlRewrites($product, $retry + 1);
            } else {
                return null;
            }
        }

        return $product->getDataUsingMethod('url_path');
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
}
