<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Console\Script;

use FeWeDev\Base\Variables;
use Infrangible\Core\Console\Command\Script;
use Infrangible\IndexPartial\Helper\Data;
use Infrangible\IndexPartial\Model\TransientIndexerFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class CategoryUrlRewrite extends Script
{
    /** @var TransientIndexerFactory */
    protected $transientIndexerFactory;

    /** @var Variables */
    protected $variables;

    public function __construct(TransientIndexerFactory $transientIndexerFactory, Variables $variables)
    {
        $this->transientIndexerFactory = $transientIndexerFactory;
        $this->variables = $variables;
    }

    /**
     * @throws Throwable
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        $indexer = $this->transientIndexerFactory->create();

        $indexer->setId(Data::PROCESS_CATALOG_URL_REWRITE_CATEGORY);
        $indexer->setData(
            'action_class',
            \Infrangible\IndexPartial\Model\TransientIndexer\CategoryUrlRewrite::class
        );

        $categoryId = $input->getOption('category_id');

        if (! $this->variables->isEmpty($categoryId)) {
            $indexer->reindexList(
                explode(
                    ',',
                    trim($categoryId)
                )
            );
        } else {
            $indexer->reindexAll();
        }

        $resultTime = microtime(true) - $startTime;

        $output->writeln(
            sprintf(
                'Category url rewrite has been rebuilt successfully in %s',
                gmdate(
                    'H:i:s',
                    $this->variables->intValue(round($resultTime))
                )
            )
        );

        return 0;
    }
}
