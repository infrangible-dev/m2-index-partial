<?php

namespace Infrangible\IndexPartial\Model\Indexer\Category;

use Exception;
use Infrangible\IndexPartial\Model\Indexer;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Flat
    extends Indexer
{
    /**
     * @throws Exception
     */
    protected function performReindex()
    {
        $allEntityIds = $this->getAllEntityIds();

        $this->logging->debug(sprintf('Updating category flat index for %d category(s)', count($allEntityIds)));

        if ( ! $this->isTest()) {
            $this->getIndexer()->reindexList($allEntityIds);
        }

        $this->logging->info(sprintf('Category flat index was updated for %d category(s)', count($allEntityIds)));
    }

    /**
     * @return int
     */
    protected function getFullIndexThreshold(): int
    {
        return static::NO_THRESHOLD;
    }
}
