<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Model;

use Magento\Indexer\Model\Indexer;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class TransientIndexer extends Indexer
{
    /**
     * @param int $id
     */
    public function reindexRow($id): void
    {
        $this->getActionInstance()->executeRow($id);
    }

    /**
     * @param int[] $ids
     */
    public function reindexList($ids): void
    {
        $this->getActionInstance()->executeList($ids);
    }

    public function reindexAll(): void
    {
        $this->getActionInstance()->executeFull();
    }

    public function isScheduled(): bool
    {
        return false;
    }
}
