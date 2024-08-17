<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Model\Indexer\Category;

use Exception;
use Infrangible\IndexPartial\Model\Indexer;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Product extends Indexer
{
    /**
     * @throws Exception
     */
    protected function performReindex(): void
    {
        $allEntityIds = $this->getAllEntityIds();

        $this->logging->debug(
            sprintf(
                'Updating category product index for %d article(s)',
                count($allEntityIds)
            )
        );

        if (! $this->isTest()) {
            $this->getIndexer()->reindexList($allEntityIds);
        }

        $this->logging->info(
            sprintf(
                'Category product index was updated for %d article(s)',
                count($allEntityIds)
            )
        );
    }

    protected function getFullIndexThreshold(): int
    {
        return static::NO_THRESHOLD;
    }
}
