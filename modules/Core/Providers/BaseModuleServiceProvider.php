<?php

declare(strict_types=1);

namespace Modules\Core\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

abstract class BaseModuleServiceProvider extends ServiceProvider
{
    /**
     * Get the name of the module.
     */
    abstract protected function getModuleName(): string;

    /**
     * Get the absolute path to the module root directory.
     */
    abstract protected function getModulePath(): string;

    /**
     * Register any module services.
     */
    public function register(): void
    {
        // Hook for subclasses
    }

    /**
     * Bootstrap any module services.
     */
    public function boot(): void
    {
        $this->loadModuleMigrations();
        $this->loadModuleViews();
        $this->loadModuleRoutes();
    }

    /**
     * Load migrations for the module if they exist.
     */
    protected function loadModuleMigrations(): void
    {
        $migrationPath = $this->getModulePath() . '/database/migrations';
        if (is_dir($migrationPath)) {
            $this->loadMigrationsFrom($migrationPath);
        }
    }

    /**
     * Load views for the module if they exist.
     */
    protected function loadModuleViews(): void
    {
        $viewPath = $this->getModulePath() . '/resources/views';
        if (is_dir($viewPath)) {
            $this->loadViewsFrom($viewPath, strtolower($this->getModuleName()));
        }
    }

    /**
     * Load routes for the module if they exist.
     */
    protected function loadModuleRoutes(): void
    {
        $routesPath = $this->getModulePath() . '/routes';

        if (is_dir($routesPath)) {
            $webRoute = $routesPath . '/web.php';
            if (file_exists($webRoute)) {
                Route::middleware('web')
                    ->group($webRoute);
            }

            $apiRoute = $routesPath . '/api.php';
            if (file_exists($apiRoute)) {
                Route::middleware('api')
                    ->prefix('api/' . strtolower($this->getModuleName()))
                    ->group($apiRoute);
            }
        }
    }
}
