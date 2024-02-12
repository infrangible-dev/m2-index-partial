<?php

namespace Infrangible\IndexPartial\Console\Script;

use Infrangible\Core\Console\Command\Script;
use Infrangible\IndexPartial\Helper\DataHelper;
use Infrangible\IndexPartial\Model\TransientIndexerFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ProductUrlRewrite
    extends Script
{
    /** @var TransientIndexerFactory */
    protected $transientIndexerFactory;

    /**
     * @param TransientIndexerFactory $transientIndexerFactory
     */
    public function __construct(TransientIndexerFactory $transientIndexerFactory)
    {
        $this->transientIndexerFactory = $transientIndexerFactory;
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int 0 if everything went fine, or an error code
     * @throws Throwable
     */
    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $startTime = microtime(true);

        $indexer = $this->transientIndexerFactory->create();

        $indexer->setId(DataHelper::PROCESS_CATALOG_URL_REWRITE_PRODUCT);
        $indexer->setData('action_class', \Infrangible\IndexPartial\Model\TransientIndexer\ProductUrlRewrite::class);

        $productId = $input->getOption('product_id');

        if ( ! empty(trim($productId))) {
            $indexer->reindexList(explode(',', $productId));
        } else {
            $indexer->reindexAll();
        }

        $resultTime = microtime(true) - $startTime;

        $output->writeln(sprintf('Product url rewrite has been rebuilt successfully in %s',
            gmdate('H:i:s', $resultTime)));

        return 0;
    }
}
