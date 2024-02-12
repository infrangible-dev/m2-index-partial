<?php

namespace Infrangible\IndexPartial\Model\Indexer\Product;

use Exception;
use Infrangible\IndexPartial\Model\Indexer;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class Url
    extends Indexer
{
    /**
     * @throws Exception
     */
    protected function performReindex()
    {
        $allEntityIds = $this->getAllEntityIds();

        $this->logging->debug(sprintf('Updating product url rewrite index for %d article(s)', count($allEntityIds)));

        if ( ! $this->isTest()) {
            $this->getIndexer()->reindexList($allEntityIds);
        }

        $this->logging->info(sprintf('Product url rewrite index was updated for %d article(s)', count($allEntityIds)));
    }

    /**
     * @return int
     */
    protected function getFullIndexThreshold(): int
    {
        return static::NO_THRESHOLD;
    }
}
