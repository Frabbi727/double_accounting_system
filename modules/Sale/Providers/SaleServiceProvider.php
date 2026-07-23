<?php

declare(strict_types=1);

namespace Modules\Sale\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class SaleServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Sale';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
