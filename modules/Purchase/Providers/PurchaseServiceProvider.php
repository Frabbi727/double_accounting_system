<?php

declare(strict_types=1);

namespace Modules\Purchase\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class PurchaseServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Purchase';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
