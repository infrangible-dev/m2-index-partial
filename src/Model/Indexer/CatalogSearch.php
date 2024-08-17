<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Model\Indexer;

use Exception;
use FeWeDev\Base\Arrays;
use Infrangible\Core\Helper\Attribute;
use Infrangible\Core\Helper\Database;
use Infrangible\Core\Helper\Stores;
use Infrangible\IndexPartial\Model\Indexer;
use Psr\Log\LoggerInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class CatalogSearch extends Indexer
{
    /** @var Arrays */
    protected $arrays;

    public function __construct(
        LoggerInterface $logging,
        Attribute $attributeHelper,
        Database $databaseHelper,
        Stores $storeHelper,
        Arrays $arrays
    ) {
        parent::__construct(
            $logging,
            $attributeHelper,
            $databaseHelper,
            $storeHelper
        );

        $this->arrays = $arrays;
    }

    /**
     * @throws Exception
     */
    protected function performReindex(): void
    {
        $allEntityIds = $this->getAllEntityIds();

        $this->logging->debug(
            sprintf(
                'Updating catalog search index for %d article(s)',
                count($allEntityIds)
            )
        );

        if (! $this->isTest()) {
            $this->getIndexer()->reindexList($allEntityIds);
        }

        $this->logging->info(
            sprintf(
                'Catalog search index was updated for %d article(s)',
                count($allEntityIds)
            )
        );
    }

    protected function getFullIndexThreshold(): int
    {
        return static::NO_THRESHOLD;
    }
}
