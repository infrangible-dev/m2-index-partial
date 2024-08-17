<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Model;

use Exception;
use Infrangible\Core\Helper\Attribute;
use Infrangible\Core\Helper\Database;
use Infrangible\Core\Helper\Stores;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
abstract class Indexer
{
    /** @var int */
    public const NO_THRESHOLD = -1;

    /** @var Attribute */
    protected $attributeHelper;

    /** @var Database */
    protected $databaseHelper;

    /** @var Stores */
    protected $storeHelper;

    /** @var LoggerInterface */
    protected $logging;

    /** @var \Magento\Indexer\Model\Indexer */
    private $indexer;

    /** @var bool */
    private $test = false;

    /** @var array */
    private $indexData;

    public function __construct(
        LoggerInterface $logging,
        Attribute $attributeHelper,
        Database $databaseHelper,
        Stores $storeHelper
    ) {
        $this->attributeHelper = $attributeHelper;
        $this->databaseHelper = $databaseHelper;
        $this->storeHelper = $storeHelper;

        $this->logging = $logging;
    }

    public function getIndexer(): \Magento\Indexer\Model\Indexer
    {
        return $this->indexer;
    }

    public function setIndexer(\Magento\Indexer\Model\Indexer $indexer)
    {
        $this->indexer = $indexer;
    }

    public function isTest(): bool
    {
        return $this->test === true;
    }

    public function setTest(bool $test = true): void
    {
        $this->test = $test;
    }

    /**
     * Data must look like this:
     * array(
     *   store_id => array(
     *     entity_id1 => array(
     *        additional_data_key1 => additional_data_value1
     *        additional_data_key2 => additional_data_value2
     *     ),
     *     entity_id2 => array(
     *        additional_data_key1 => additional_data_value1
     *        additional_data_key2 => additional_data_value2
     *     )
     *   )
     * )
     *
     * @param array $indexData
     *
     * @throws Exception
     */
    public function prepareData(array $indexData)
    {
        if (! empty($indexData)) {
            foreach ($indexData as $storeId => $entityData) {
                if (! is_numeric($storeId)) {
                    throw new Exception(
                        sprintf(
                            'Invalid store id: %s',
                            $storeId
                        )
                    );
                }

                if (! is_array($entityData)) {
                    throw new Exception(
                        sprintf(
                            'Invalid entity data for store with id: %d: %s',
                            $storeId,
                            trim(
                                print_r(
                                    $entityData,
                                    true
                                )
                            )
                        )
                    );
                }

                foreach ($entityData as $entityId => $additionalData) {
                    if (! is_numeric($entityId)) {
                        throw new Exception(
                            sprintf(
                                'Invalid entity id: %s in store with id: %d',
                                $entityId,
                                $storeId
                            )
                        );
                    }

                    if (! is_array($additionalData)) {
                        throw new Exception(
                            sprintf(
                                'Invalid additional data for entity id: %d in store with id: %d: %s',
                                $entityId,
                                $storeId,
                                trim(
                                    print_r(
                                        $additionalData,
                                        true
                                    )
                                )
                            )
                        );
                    }
                }
            }
        }

        $this->indexData = $indexData;
    }

    /**
     * @throws Exception
     */
    private function checkDataPrepared()
    {
        if (is_null($this->indexData)) {
            throw new Exception('No index data defined!');
        }
    }

    /**
     * @throws Exception
     */
    public function shouldRunFullIndex(): bool
    {
        $this->checkDataPrepared();

        $runFullIndex = false;

        $fullIndexThreshold = $this->getFullIndexThreshold();

        if ($fullIndexThreshold !== static::NO_THRESHOLD) {
            $entityCount = 0;

            foreach ($this->indexData as $entityData) {
                $entityCount += count($entityData);
            }

            if ($entityCount > $fullIndexThreshold) {
                $runFullIndex = true;
            }
        }

        return $runFullIndex;
    }

    abstract protected function getFullIndexThreshold(): int;

    /**
     * @throws Exception
     */
    public function execute()
    {
        $this->checkDataPrepared();
        $this->performReindex();
    }

    abstract protected function performReindex(): void;

    /**
     * @throws Exception
     */
    public function getIndexData(): array
    {
        $this->checkDataPrepared();

        return $this->indexData;
    }

    /**
     * @return int[]
     * @throws Exception
     */
    public function getAllEntityIds(): array
    {
        $this->checkDataPrepared();

        $allEntityIds = [];

        foreach ($this->indexData as $entityIds) {
            $allEntityIds = array_merge(
                $allEntityIds,
                array_keys($entityIds)
            );
        }

        return array_unique($allEntityIds);
    }

    /**
     * @return int[]
     * @throws Exception
     */
    protected function getEntityWebsiteIds(): array
    {
        $this->checkDataPrepared();

        $entityWebsiteIds = [];

        foreach ($this->indexData as $storeId => $entityIds) {
            $websiteId = $this->storeHelper->getStore($storeId)->getWebsiteId();

            foreach ($entityIds as $entityId => $additionalData) {
                if (! array_key_exists(
                    $entityId,
                    $entityWebsiteIds
                )) {
                    $entityWebsiteIds[ $entityId ] = [];
                }

                if (! array_key_exists(
                    $websiteId,
                    $entityWebsiteIds[ $entityId ]
                )) {
                    $entityWebsiteIds[ $entityId ][] = $websiteId;
                }
            }
        }

        return $entityWebsiteIds;
    }

    /**
     * @return int[]
     * @throws Exception
     */
    protected function getWebsiteEntityIds(): array
    {
        $this->checkDataPrepared();

        $websiteEntityIds = [];

        foreach ($this->indexData as $storeId => $entityIds) {
            $websiteId = $this->storeHelper->getStore($storeId)->getWebsiteId();

            if (! array_key_exists(
                $websiteId,
                $websiteEntityIds
            )) {
                $websiteEntityIds[ $websiteId ] = [];
            }

            $websiteEntityIds[ $websiteId ] = array_unique(
                array_merge(
                    $websiteEntityIds[ $websiteId ],
                    array_keys($entityIds)
                )
            );
        }

        return $websiteEntityIds;
    }

    /**
     * @throws Exception
     */
    protected function getStoreEntitiesByStatus(): array
    {
        $this->checkDataPrepared();

        $storeEntitiesByStatus = [];

        foreach ($this->indexData as $storeId => $entityIds) {
            $updateEntityIds = [];
            $removeEntityIds = [];

            foreach ($entityIds as $entityId => $additionalData) {
                if (empty($additionalData) || ! array_key_exists(
                        'status',
                        $additionalData
                    )) {
                    $status = $this->attributeHelper->getAttributeValue(
                        $this->databaseHelper->getDefaultConnection(),
                        Product::ENTITY,
                        'status',
                        $entityId,
                        $storeId,
                        true,
                        true
                    );
                } else {
                    $status = $additionalData[ 'status' ];
                }

                if ($status != Status::STATUS_DISABLED) {
                    $updateEntityIds[] = $entityId;
                } else {
                    $removeEntityIds[] = $entityId;
                }
            }

            $storeEntitiesByStatus[ $storeId ] = [
                'update' => $updateEntityIds,
                'remove' => $removeEntityIds
            ];
        }

        return $storeEntitiesByStatus;
    }
}
