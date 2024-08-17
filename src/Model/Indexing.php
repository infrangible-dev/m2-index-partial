<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Model;

use Exception;
use FeWeDev\Base\Variables;
use Infrangible\Core\Helper\Index;
use Infrangible\Core\Helper\Stores;
use Infrangible\IndexPartial\Helper\Data;
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
    protected $variables;

    /** @var Stores */
    protected $storeHelper;

    /** @var Index */
    protected $indexHelper;

    /** @var Data */
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

    public function __construct(
        Variables $variables,
        Stores $storeHelper,
        Index $indexHelper,
        Data $dataHelper,
        LoggerInterface $logging,
        Manager $eventManager,
        IndexerFactory $indexerFactory,
        TransientIndexerFactory $transientIndexerFactory,
        StateFactory $stateResourceFactory,
        State $categoryFlatState,
        \Magento\Catalog\Model\Indexer\Product\Flat\State $productFlatState
    ) {
        $this->variables = $variables;
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

    public function getIndexEvents(): array
    {
        return $this->indexEvents;
    }

    /**
     * @throws Exception
     */
    public function addIndexEvent(string $code, int $entityId, int $storeId, array $additionalData = []): void
    {
        if (empty($code)) {
            throw new Exception('Trying to add index event without any code');
        }

        if (empty($entityId)) {
            throw new Exception(
                sprintf(
                    'Trying to add index event without entity id for index with code: %s',
                    $code
                )
            );
        }

        if (! is_numeric($entityId)) {
            throw new Exception(
                sprintf(
                    'Trying to add index event with invalid entity id: %s for index with code: %s',
                    $entityId,
                    $code
                )
            );
        }

        if ($this->variables->isEmpty($storeId)) {
            throw new Exception(
                sprintf(
                    'Trying to add index event without store id for index with code: %s and entity id: %d',
                    $code,
                    $entityId
                )
            );
        }

        if (! is_numeric($storeId)) {
            throw new Exception(
                sprintf(
                    'Trying to add index event with invalid store id: %s for index with code: %s and entity id: %d',
                    $storeId,
                    $code,
                    $entityId
                )
            );
        }

        if ($storeId == 0) {
            foreach ($this->storeHelper->getStores() as $store) {
                $this->addIndexEvent(
                    $code,
                    $entityId,
                    $this->variables->intValue($store->getId()),
                    $additionalData
                );
            }

            return;
        }

        if (! array_key_exists(
            $code,
            $this->indexEvents
        )) {
            $this->indexEvents[ $code ] = [];
        }

        if (! array_key_exists(
            $storeId,
            $this->indexEvents[ $code ]
        )) {
            $this->indexEvents[ $code ][ $storeId ] = [];
        }

        if (! array_key_exists(
            $entityId,
            $this->indexEvents[ $code ][ $storeId ]
        )) {
            $this->indexEvents[ $code ][ $storeId ][ $entityId ] = [];
        }

        $this->indexEvents[ $code ][ $storeId ][ $entityId ] = array_merge(
            $this->indexEvents[ $code ][ $storeId ][ $entityId ],
            $additionalData
        );
    }

    /**
     * @throws Throwable
     */
    public function reindex(): void
    {
        $indexer = [
            Data::PROCESS_CATALOGINVENTORY_STOCK,
            Data::PROCESS_INVENTORY_STOCK,
            Data::PROCESS_CATALOG_PRODUCT_ATTRIBUTE,
            Data::PROCESS_CATALOG_CATEGORY_PRODUCT,
            Data::PROCESS_CATALOG_PRODUCT_CATEGORY,
            Data::PROCESS_CATALOG_PRODUCT_PRICE,
            Data::PROCESS_CATALOG_URL_REWRITE_CATEGORY,
            Data::PROCESS_CATALOG_URL_REWRITE_PRODUCT,
            Data::PROCESS_CATALOGSEARCH_FULLTEXT
        ];

        if ($this->productFlatState->isFlatEnabled()) {
            $indexer[] = Data::PROCESS_CATALOG_PRODUCT_FLAT;
        }

        if ($this->categoryFlatState->isFlatEnabled()) {
            $indexer[] = Data::PROCESS_CATALOG_CATEGORY_FLAT;
        }

        $reindex = new DataObject([
            'indexer'      => $indexer,
            'index_events' => $this->indexEvents
        ]);

        $this->eventManager->dispatch(
            'infrangible_indexpartial_reindex_before',
            ['reindex' => $reindex]
        );

        $this->indexEvents = $reindex->getData('index_events');

        foreach ($reindex->getData('indexer') as $code) {
            $this->reindexProcess($code);
        }

        $this->eventManager->dispatch(
            'infrangible_indexpartial_reindex_reindex_after',
            ['reindex' => $reindex]
        );
    }

    /**
     * @throws Throwable
     */
    protected function reindexProcess(string $code): void
    {
        if (! array_key_exists(
            $code,
            $this->indexEvents
        )) {
            return;
        }

        $indexer = $this->getIndexerByCode($code);

        if ($indexer->isScheduled()) {
            $this->setIndexModeRequireReindex($indexer);
        } else {
            $partialIndexer = $this->dataHelper->getIndexer($indexer);

            if (! $partialIndexer) {
                throw new Exception(
                    sprintf(
                        'No indexer found was index process with code: %s',
                        $code
                    )
                );
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
     * @throws Exception
     */
    public function setIndexModeRequireReindex(Indexer $indexer): void
    {
        if ($indexer->getStatus() != StateInterface::STATUS_INVALID) {
            $this->logging->info(
                sprintf(
                    'Setting index with name: %s to status: %s',
                    $indexer->getId(),
                    StateInterface::STATUS_INVALID
                )
            );

            if (! $this->isTest()) {
                /** @var Indexer\State $state */
                $state = $indexer->getState();

                $state->setStatus(StateInterface::STATUS_INVALID);

                $this->stateResourceFactory->create()->save($state);
            }
        }
    }

    public function getIndexerByCode(string $code): Indexer
    {
        $this->logging->debug(
            sprintf(
                'Loading indexer with code: %s',
                $code
            )
        );

        if ($code == Data::PROCESS_CATALOG_URL_REWRITE_CATEGORY || $code == Data::PROCESS_CATALOG_URL_REWRITE_PRODUCT) {
            $indexer = $this->transientIndexerFactory->create();

            $indexer->setId($code);
            $indexer->setData(
                'action_class',
                $code == Data::PROCESS_CATALOG_URL_REWRITE_CATEGORY ? CategoryUrlRewrite::class :
                    ProductUrlRewrite::class
            );
        } else {
            try {
                $indexer = $this->indexerFactory->create();

                $indexer->load($code);

                if (! $indexer->getId()) {
                    $this->logging->error(
                        sprintf(
                            'Could not load index process with code: %s',
                            $code
                        )
                    );

                    $indexer = null;
                }
            } catch (Exception $exception) {
                $this->logging->error(
                    sprintf(
                        'Could not load index process with code: %s',
                        $code
                    )
                );
                $this->logging->error($exception);

                $indexer = null;
            }
        }

        return $indexer;
    }

    public function isTest(): bool
    {
        return $this->test === true;
    }

    public function setTest(bool $test = true): void
    {
        $this->test = $test;
    }
}
