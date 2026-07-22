<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

class CoreServiceProvider extends BaseModuleServiceProvider
{
    /**
     * Get the name of the module.
     */
    protected function getModuleName(): string
    {
        return 'Core';
    }

    /**
     * Get the absolute path to the module root directory.
     */
    protected function getModulePath(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Register any core services.
     */
    public function register(): void
    {
        parent::register();

        // Bind global core services here (e.g., Audit logs, common repositories)
    }

    /**
     * Bootstrap any core services.
     */
    public function boot(): void
    {
        parent::boot();
    }
}
