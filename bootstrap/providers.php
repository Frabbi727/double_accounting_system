<?php

use App\Providers\AppServiceProvider;
use Modules\Core\Providers\CoreServiceProvider;
use Modules\Core\Providers\ModuleAutoloadServiceProvider;

return [
    AppServiceProvider::class,
    CoreServiceProvider::class,
    ModuleAutoloadServiceProvider::class,
];
