<?php

declare(strict_types=1);

namespace Modules\Accounting\Providers;

use Modules\Core\Providers\BaseModuleServiceProvider;

class AccountingServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Get the name of the module.
     */
    protected function getModuleName(): string
    {
        return 'Accounting';
    }

    /**
     * Get the absolute path to the module root directory.
     */
    protected function getModulePath(): string
    {
        return dirname(__DIR__);
    }

    /**
     * Register any accounting services.
     */
    public function register(): void
    {
        parent::register();

        // The accounting services are plain, auto-resolvable classes; Laravel's
        // container wires their constructor dependencies without explicit binds.
    }

    /**
     * Bootstrap any accounting services.
     *
     * BaseModuleServiceProvider::boot() auto-loads this module's
     * database/migrations and routes directories.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
