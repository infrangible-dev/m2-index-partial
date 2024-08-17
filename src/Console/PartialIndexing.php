<?php

declare(strict_types=1);

namespace Infrangible\IndexPartial\Console;

use Infrangible\Core\Console\Command\Command;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class PartialIndexing extends Command
{
    protected function getCommandName(): string
    {
        return 'indexer:partial-indexer';
    }

    protected function getCommandDescription(): string
    {
        return 'Re-index indexes for specific entities';
    }

    protected function getCommandDefinition(): array
    {
        return [
            new InputOption(
                'indexer',
                'i',
                InputOption::VALUE_REQUIRED,
                'Re-index only this index or indexers separated by comma'
            ),
            new InputOption(
                'entity_id',
                'e',
                InputOption::VALUE_REQUIRED,
                'Re-index only this entity or entities separated by comma'
            ),
            new InputOption(
                'store',
                's',
                InputOption::VALUE_OPTIONAL,
                'Re-index only this store or stores by comma'
            )
        ];
    }

    protected function getClassName(): string
    {
        return Script\PartialIndexing::class;
    }

    protected function getArea(): string
    {
        return Area::AREA_ADMINHTML;
    }
}
