<?php

declare(strict_types=1);

namespace Modules\Finance\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class FinanceServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Finance';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
