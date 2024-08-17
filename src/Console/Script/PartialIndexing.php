<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Console\Script;

use FeWeDev\Base\Variables;
use Infrangible\Core\Console\Command\Script;
use Infrangible\IndexPartial\Model\Indexing;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class PartialIndexing extends Script
{
    /** @var Variables */
    protected $variables;

    /** @var Indexing */
    protected $indexing;

    public function __construct(Variables $variables, Indexing $indexing)
    {
        $this->variables = $variables;
        $this->indexing = $indexing;
    }

    /**
     * @throws \Throwable
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $indexer = $input->getOption('indexer');
        $indexers = explode(
            ',',
            trim($indexer)
        );

        $entityId = $input->getOption('entity_id');
        $entityIds = explode(
            ',',
            trim($entityId)
        );

        $storeId = $input->getOption('store');
        $storeIds = $this->variables->isEmpty($storeId) ? [0] : explode(
            ',',
            $storeId
        );

        foreach ($indexers as $indexer) {
            foreach ($entityIds as $entityId) {
                foreach ($storeIds as $storeId) {
                    $this->indexing->addIndexEvent(
                        $indexer,
                        $this->variables->intValue($entityId),
                        $this->variables->intValue($storeId)
                    );
                }
            }
        }

        $startTime = microtime(true);

        $this->indexing->reindex();

        $resultTime = microtime(true) - $startTime;

        $output->writeln(
            sprintf(
                'Indexes have been rebuilt successfully in %s',
                gmdate(
                    'H:i:s',
                    $this->variables->intValue(round($resultTime))
                )
            )
        );

        return 0;
    }
}