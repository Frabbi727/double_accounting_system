<?php

declare(strict_types=1);

namespace Modules\Asset\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class AssetServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Asset';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
