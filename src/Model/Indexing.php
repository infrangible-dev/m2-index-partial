<?php

namespace Infrangible\IndexPartial\Model;

use Exception;
use Infrangible\Core\Helper\Index;
use Infrangible\Core\Helper\Stores;
use Infrangible\IndexPartial\Helper\DataHelper;
use Infrangible\IndexPartial\Model\TransientIndexer\CategoryUrlRewrite;
use Infrangible\IndexPartial\Model\TransientIndexer\ProductUrlRewrite;
use Magento\Catalog\Model\Indexer\Category\Flat\State;
use Magento\Framework\DataObject;
use Magento\Framework\Event\Manager;
use Magento\Framework\Indexer\StateInterface;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Indexer\Model\ResourceModel\Indexer\StateFactory;
use Psr\Log\LoggerInterface;
use Throwable;
use Tofex\Help\Variables;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Indexing
{
    /** @var LoggerInterface */
    protected $logging;

    /** @var Manager */
    protected $eventManager;

    /** @var Variables */
    protected $variableHelper;

    /** @var Stores */
    protected $storeHelper;

    /** @var Index */
    protected $indexHelper;

    /** @var DataHelper */
    protected $dataHelper;

    /** @var IndexerFactory */
    protected $indexerFactory;

    /** @var TransientIndexerFactory */
    protected $transientIndexerFactory;

    /** @var StateFactory */
    protected $stateResourceFactory;

    /** @var State */
    protected $categoryFlatState;

    /** @var \Magento\Catalog\Model\Indexer\Product\Flat\State */
    protected $productFlatState;

    /** @var bool */
    private $test = false;

    /** @var array */
    private $indexEvents = [];

    /**
     * @param Variables                                         $variableHelper
     * @param Stores                                            $storeHelper
     * @param Index                                             $indexHelper
     * @param DataHelper                                        $dataHelper
     * @param LoggerInterface                                   $logging
     * @param Manager                                           $eventManager
     * @param IndexerFactory                                    $indexerFactory
     * @param TransientIndexerFactory                           $transientIndexerFactory
     * @param StateFactory                                      $stateResourceFactory
     * @param State                                             $categoryFlatState
     * @param \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState
     */
    public function __construct(
        Variables $variableHelper,
        Stores $storeHelper,
        Index $indexHelper,
        DataHelper $dataHelper,
        LoggerInterface $logging,
        Manager $eventManager,
        IndexerFactory $indexerFactory,
        TransientIndexerFactory $transientIndexerFactory,
        StateFactory $stateResourceFactory,
        State $categoryFlatState,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState)
    {
        $this->variableHelper = $variableHelper;
        $this->storeHelper = $storeHelper;
        $this->indexHelper = $indexHelper;
        $this->dataHelper = $dataHelper;

        $this->logging = $logging;
        $this->eventManager = $eventManager;
        $this->indexerFactory = $indexerFactory;
        $this->transientIndexerFactory = $transientIndexerFactory;
        $this->stateResourceFactory = $stateResourceFactory;
        $this->categoryFlatState = $categoryFlatState;
        $this->productFlatState = $productFlatState;
    }

    /**
     * @return array
     */
    public function getIndexEvents(): array
    {
        return $this->indexEvents;
    }

    /**
     * @param string $code
     * @param int    $entityId
     * @param int    $storeId
     * @param array  $additionalData
     *
     * @throws Exception
     */
    public function addIndexEvent(string $code, int $entityId, int $storeId, array $additionalData = [])
    {
        if (empty($code)) {
            throw new Exception('Trying to add index event without any code');
        }

        if (empty($entityId)) {
            throw new Exception(sprintf('Trying to add index event without entity id for index with code: %s', $code));
        }

        if ( ! is_numeric($entityId)) {
            throw new Exception(sprintf('Trying to add index event with invalid entity id: %s for index with code: %s',
                $entityId, $code));
        }

        if ($this->variableHelper->isEmpty($storeId)) {
            throw new Exception(sprintf('Trying to add index event without store id for index with code: %s and entity id: %d',
                $code, $entityId));
        }

        if ( ! is_numeric($storeId)) {
            throw new Exception(sprintf('Trying to add index event with invalid store id: %s for index with code: %s and entity id: %d',
                $storeId, $code, $entityId));
        }

        if ($storeId == 0) {
            foreach ($this->storeHelper->getStores() as $store) {
                $this->addIndexEvent($code, $entityId, $store->getId(), $additionalData);
            }

            return;
        }

        if ( ! array_key_exists($code, $this->indexEvents)) {
            $this->indexEvents[ $code ] = [];
        }

        if ( ! array_key_exists($storeId, $this->indexEvents[ $code ])) {
            $this->indexEvents[ $code ][ $storeId ] = [];
        }

        if ( ! array_key_exists($entityId, $this->indexEvents[ $code ][ $storeId ])) {
            $this->indexEvents[ $code ][ $storeId ][ $entityId ] = [];
        }

        $this->indexEvents[ $code ][ $storeId ][ $entityId ] =
            array_merge($this->indexEvents[ $code ][ $storeId ][ $entityId ], $additionalData);
    }

    /**
     * Process the previously acquired index events
     *
     * @throws Exception
     * @throws Throwable
     */
    public function reindex()
    {
        $indexer = [
            DataHelper::PROCESS_CATALOGINVENTORY_STOCK,
            DataHelper::PROCESS_INVENTORY_STOCK,
            DataHelper::PROCESS_CATALOG_PRODUCT_ATTRIBUTE,
            DataHelper::PROCESS_CATALOG_CATEGORY_PRODUCT,
            DataHelper::PROCESS_CATALOG_PRODUCT_CATEGORY,
            DataHelper::PROCESS_CATALOG_PRODUCT_PRICE,
            DataHelper::PROCESS_CATALOG_URL_REWRITE_CATEGORY,
            DataHelper::PROCESS_CATALOG_URL_REWRITE_PRODUCT,
            DataHelper::PROCESS_CATALOGSEARCH_FULLTEXT
        ];

        if ($this->productFlatState->isFlatEnabled()) {
            $indexer[] = DataHelper::PROCESS_CATALOG_PRODUCT_FLAT;
        }

        if ($this->categoryFlatState->isFlatEnabled()) {
            $indexer[] = DataHelper::PROCESS_CATALOG_CATEGORY_FLAT;
        }

        $reindex = new DataObject([
            'indexer'      => $indexer,
            'index_events' => $this->indexEvents
        ]);

        $this->eventManager->dispatch('infrangible_indexpartial_reindex_before', ['reindex' => $reindex]);

        $this->indexEvents = $reindex->getData('index_events');

        foreach ($reindex->getData('indexer') as $code) {
            $this->reindexProcess($code);
        }

        $this->eventManager->dispatch('infrangible_indexpartial_reindex_reindex_after', ['reindex' => $reindex]);
    }

    /**
     * @param string $code
     *
     * @throws Exception
     * @throws Throwable
     */
    protected function reindexProcess(string $code)
    {
        if ( ! array_key_exists($code, $this->indexEvents)) {
            return;
        }

        $indexer = $this->getIndexerByCode($code);

        if ($indexer->isScheduled()) {
            $this->setIndexModeRequireReindex($indexer);
        } else {
            $partialIndexer = $this->dataHelper->getIndexer($indexer);

            if ( ! $partialIndexer) {
                throw new Exception(sprintf('No indexer found was index process with code: %s', $code));
            }

            $partialIndexer->setTest($this->isTest());

            $partialIndexer->prepareData($this->indexEvents[ $code ]);

            if ($partialIndexer->shouldRunFullIndex()) {
                $this->indexHelper->runIndexProcess($indexer);
            } else {
                $this->dataHelper->executePartialIndexer($partialIndexer);
            }
        }
    }

    /**
     * @param Indexer $indexer
     *
     * @throws Exception
     */
    public function setIndexModeRequireReindex(Indexer $indexer)
    {
        if ($indexer->getStatus() != StateInterface::STATUS_INVALID) {
            $this->logging->info(sprintf('Setting index with name: %s to status: %s', $indexer->getId(),
                StateInterface::STATUS_INVALID));

            if ( ! $this->isTest()) {
                /** @var Indexer\State $state */
                $state = $indexer->getState();

                $state->setStatus(StateInterface::STATUS_INVALID);

                $this->stateResourceFactory->create()->save($state);
            }
        }
    }

    /**
     * @param string $code
     *
     * @return Indexer
     */
    public function getIndexerByCode(string $code): Indexer
    {
        $this->logging->debug(sprintf('Loading indexer with code: %s', $code));

        if ($code == DataHelper::PROCESS_CATALOG_URL_REWRITE_CATEGORY ||
            $code == DataHelper::PROCESS_CATALOG_URL_REWRITE_PRODUCT) {
            $indexer = $this->transientIndexerFactory->create();

            $indexer->setId($code);
            $indexer->setData('action_class',
                $code == DataHelper::PROCESS_CATALOG_URL_REWRITE_CATEGORY ? CategoryUrlRewrite::class :
                    ProductUrlRewrite::class);
        } else {
            try {
                $indexer = $this->indexerFactory->create();

                $indexer->load($code);

                if ( ! $indexer->getId()) {
                    $this->logging->error(sprintf('Could not load index process with code: %s', $code));

                    $indexer = null;
                }
            } catch (Exception $exception) {
                $this->logging->error(sprintf('Could not load index process with code: %s', $code));
                $this->logging->error($exception);

                $indexer = null;
            }
        }

        return $indexer;
    }

    /**
     * @return bool
     */

    public function isTest(): bool
    {
        return $this->test === true;
    }

    /**
     * @param bool $test
     *
     * @return void
     */

    public function setTest(bool $test = true)
    {
        $this->test = $test;
    }
}
