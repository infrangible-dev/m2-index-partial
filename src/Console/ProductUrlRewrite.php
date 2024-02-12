<?php

namespace Infrangible\IndexPartial\Console;

use Infrangible\Core\Console\Command\Command;
use Magento\Framework\App\Area;
use Symfony\Component\Console\Input\InputOption;

/**
 * @author      Andreas Knollmann
 * @copyright   Copyright (c) 2014-2024 Softwareentwicklung Andreas Knollmann
 * @license     http://www.opensource.org/licenses/mit-license.php MIT
 */
class ProductUrlRewrite
    extends Command
{
    /**
     * @return string
     */
    protected function getCommandName(): string
    {
        return 'indexer:url-rewrite:product';
    }

    /**
     * @return string
     */
    protected function getCommandDescription(): string
    {
        return 'Re-index all product url rewrites';
    }

    /**
     * @return array
     */
    protected function getCommandDefinition(): array
    {
        return [
            new InputOption('product_id', 'p', InputOption::VALUE_OPTIONAL, 'Re-index only this product')
        ];
    }

    /**
     * @return string
     */
    protected function getClassName(): string
    {
        return Script\ProductUrlRewrite::class;
    }

    /**
     * @return string
     */
    protected function getArea(): string
    {
        return Area::AREA_ADMINHTML;
    }
}
