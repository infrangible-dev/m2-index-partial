<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Helper;

use Exception;
use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Index;
use Infrangible\Core\Helper\Stores;
use Infrangible\IndexPartial\Model\Indexer;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Validator\UniversalFactory;
use Throwable;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Data extends AbstractHelper
{
    /** @var string */
    public const PROCESS_CATALOGINVENTORY_STOCK = 'cataloginventory_stock';

    /** @var string */
    public const PROCESS_CATALOGSEARCH_FULLTEXT = 'catalogsearch_fulltext';

    /** @var string */
    public const PROCESS_CATALOG_CATEGORY_FLAT = 'catalog_category_flat';

    /** @var string */
    public const PROCESS_CATALOG_CATEGORY_PRODUCT = 'catalog_category_product';

    /** @var string */
    public const PROCESS_CATALOG_PRODUCT_ATTRIBUTE = 'catalog_product_attribute';

    /** @var string */
    public const PROCESS_CATALOG_PRODUCT_CATEGORY = 'catalog_product_category';

    /** @var string */
    public const PROCESS_CATALOG_PRODUCT_FLAT = 'catalog_product_flat';

    /** @var string */
    public const PROCESS_CATALOG_PRODUCT_PRICE = 'catalog_product_price';

    /** @var string */
    public const PROCESS_CATALOG_URL_REWRITE_CATEGORY = 'catalog_url_category';

    /** @var string */
    public const PROCESS_CATALOG_URL_REWRITE_PRODUCT = 'catalog_url_product';

    /** @var string */
    public const PROCESS_INVENTORY_STOCK = 'inventory';

    /** @var Variables */
    protected $variables;

    /** @var Index */
    protected $indexHelper;

    /** @var Stores */
    protected $storeHelper;

    /** @var UniversalFactory */
    protected $universalFactory;

    public function __construct(
        Context $context,
        Variables $variables,
        Index $indexHelper,
        Stores $storeHelper,
        UniversalFactory $universalFactory
    ) {
        parent::__construct($context);

        $this->variables = $variables;
        $this->indexHelper = $indexHelper;
        $this->storeHelper = $storeHelper;

        $this->universalFactory = $universalFactory;
    }

    public function getIndexer(\Magento\Indexer\Model\Indexer $indexer): ?Indexer
    {
        $modelClass = $this->storeHelper->getStoreConfig(
            sprintf(
                'infrangible_indexpartial/indexer/%s',
                $indexer->getId()
            )
        );

        $model = ! $this->variables->isEmpty($modelClass) ? $this->universalFactory->create($modelClass) : null;

        /** @var Indexer $model */
        if ($model) {
            $model->setIndexer($indexer);
        }

        return $model;
    }

    /**
     * @throws Exception
     */
    public function executePartialIndexer(Indexer $indexer): void
    {
        $indexer->execute();
    }

    /**
     * @throws Throwable
     */
    public function executeFullIndexer(Indexer $indexer): void
    {
        $this->indexHelper->runIndexProcess($indexer->getIndexer());
    }
}
