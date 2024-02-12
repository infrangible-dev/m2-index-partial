<?php

namespace Infrangible\IndexPartial\Helper;

use Exception;
use Infrangible\Core\Helper\Index;
use Infrangible\Core\Helper\Stores;
use Infrangible\IndexPartial\Model\Indexer;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Validator\UniversalFactory;
use Throwable;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class DataHelper
    extends AbstractHelper
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
    protected $variableHelper;

    /** @var Index */
    protected $indexHelper;

    /** @var Stores */
    protected $storeHelper;

    /** @var UniversalFactory */
    protected $universalFactory;

    /**
     * @param Context          $context
     * @param Variables        $variableHelper
     * @param Index            $indexHelper
     * @param Stores           $storeHelper
     * @param UniversalFactory $universalFactory
     */
    public function __construct(
        Context $context,
        Variables $variableHelper,
        Index $indexHelper,
        Stores $storeHelper,
        UniversalFactory $universalFactory)
    {
        parent::__construct($context);

        $this->variableHelper = $variableHelper;
        $this->indexHelper = $indexHelper;
        $this->storeHelper = $storeHelper;

        $this->universalFactory = $universalFactory;
    }

    /**
     * @param \Magento\Indexer\Model\Indexer $indexer
     *
     * @return Indexer
     */
    public function getIndexer(\Magento\Indexer\Model\Indexer $indexer): ?Indexer
    {
        $modelClass =
            $this->storeHelper->getStoreConfig(sprintf('infrangible_indexpartial/indexer/%s', $indexer->getId()));

        $model = ! $this->variableHelper->isEmpty($modelClass) ? $this->universalFactory->create($modelClass) : null;

        /** @var Indexer $model */
        if ($model) {
            $model->setIndexer($indexer);
        }

        return $model;
    }

    /**
     * @param Indexer $indexer
     *
     * @throws Exception
     */
    public function executePartialIndexer(Indexer $indexer)
    {
        $indexer->execute();
    }

    /**
     * @param Indexer $indexer
     *
     * @throws Exception|Throwable
     */
    public function executeFullIndexer(Indexer $indexer)
    {
        $this->indexHelper->runIndexProcess($indexer->getIndexer());
    }
}
