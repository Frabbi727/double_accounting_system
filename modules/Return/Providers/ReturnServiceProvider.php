<?php

declare(strict_types=1);

namespace Modules\Return\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class ReturnServiceProvider extends BaseModuleServiceProvider
{
    protected function getModuleName(): string
    {
        return 'Return';
    }

    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }
}
