<?php

namespace Infrangible\IndexPartial\Model\Indexer;

use Exception;
use Infrangible\Core\Helper\Attribute;
use Infrangible\Core\Helper\Database;
use Infrangible\Core\Helper\Stores;
use Infrangible\IndexPartial\Model\Indexer;
use Psr\Log\LoggerInterface;
use Tofex\Help\Arrays;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class CatalogSearch
    extends Indexer
{
    /** @var Arrays */
    protected $arrayHelper;

    /**
     * @param LoggerInterface $logging
     * @param Attribute       $attributeHelper
     * @param Database        $databaseHelper
     * @param Stores          $storeHelper
     * @param Arrays          $arrayHelper
     */
    public function __construct(
        LoggerInterface $logging,
        Attribute $attributeHelper,
        Database $databaseHelper,
        Stores $storeHelper,
        Arrays $arrayHelper)
    {
        parent::__construct($logging, $attributeHelper, $databaseHelper, $storeHelper);

        $this->arrayHelper = $arrayHelper;
    }

    /**
     * @throws Exception
     */
    protected function performReindex()
    {
        $allEntityIds = $this->getAllEntityIds();

        $this->logging->debug(sprintf('Updating catalog search index for %d article(s)', count($allEntityIds)));

        if ( ! $this->isTest()) {
            $this->getIndexer()->reindexList($allEntityIds);
        }

        $this->logging->info(sprintf('Catalog search index was updated for %d article(s)', count($allEntityIds)));
    }

    /**
     * @return int
     */
    protected function getFullIndexThreshold(): int
    {
        return static::NO_THRESHOLD;
    }
}
