<?php

namespace Infrangible\IndexPartial\Model;

use Magento\Indexer\Model\Indexer;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class TransientIndexer
    extends Indexer
{
    /**
     * Regenerate one row in index by ID
     *
     * @param int $id
     *
     * @return void
     */
    public function reindexRow($id)
    {
        $this->getActionInstance()->executeRow($id);
    }

    /**
     * Regenerate rows in index by ID list
     *
     * @param int[] $ids
     *
     * @return void
     */
    public function reindexList($ids)
    {
        $this->getActionInstance()->executeList($ids);
    }

    /**
     * @return void
     */
    public function reindexAll()
    {
        $this->getActionInstance()->executeFull();
    }

    /**
     * Check whether indexer is run by schedule
     *
     * @return bool
     */
    public function isScheduled(): bool
    {
        return false;
    }
}
