<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\File;

class ModuleAutoloadServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $modulesPath = base_path('modules');

        if (!File::isDirectory($modulesPath)) {
            return;
        }

        $modules = File::directories($modulesPath);

        foreach ($modules as $modulePath) {
            $moduleName = basename($modulePath);
            
            // Skip Core module itself since it is registered globally in app.php
            if ($moduleName === 'Core') {
                continue;
            }

            $providerClass = "Modules\\{$moduleName}\\Providers\\{$moduleName}ServiceProvider";

            if (class_exists($providerClass)) {
                $this->app->register($providerClass);
            }
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
