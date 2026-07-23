<?php

declare(strict_types=1);

namespace Modules\Adjustment\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class AdjustmentServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Adjustment';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
