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
class CategoryUrlRewrite extends Command
{
    protected function getCommandName(): string
    {
        return 'indexer:url-rewrite:category';
    }

    protected function getCommandDescription(): string
    {
        return 'Re-index category url rewrite(s)';
    }

    protected function getCommandDefinition(): array
    {
        return [
            new InputOption(
                'category_id',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Re-index only this category'
            )
        ];
    }

    protected function getClassName(): string
    {
        return Script\CategoryUrlRewrite::class;
    }

    protected function getArea(): string
    {
        return Area::AREA_ADMINHTML;
    }
}
