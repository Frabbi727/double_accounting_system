<?php

declare(strict_types=1);

namespace Modules\Incentive\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class IncentiveServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Incentive';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
