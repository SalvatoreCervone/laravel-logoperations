<?php

namespace SalvatoreCervone\LogOperations;

use Illuminate\Support\ServiceProvider;
use Illuminate\Routing\Router;
use SalvatoreCervone\LogOperations\Http\Middleware\LogOperationsMiddleware;
use SalvatoreCervone\LogOperations\Services\StackTracer;

class LogOperationsServiceProvider extends ServiceProvider
{
    /**
     * Register bindings in the container.
     */
    public function register(): void
    {
        // Merge della configurazione con valori di default
        $this->mergeConfigFrom(
            __DIR__ . '/../config/logoperations.php',
            'logoperations'
        );

        // Registrazione singleton del StackTracer
        $this->app->singleton(StackTracer::class, function ($app) {
            return new StackTracer(
                config('logoperations.stack_trace', [])
            );
        });

        // Registrazione singleton del PrivacyManager
        $this->app->singleton(\SalvatoreCervone\LogOperations\Services\PrivacyManager::class);

        // Registrazione singleton del servizio LogOperations
        $this->app->singleton(LogOperationsManager::class, function ($app) {
            return new LogOperationsManager(
                $app->make(StackTracer::class),
                $app->make(\SalvatoreCervone\LogOperations\Services\PrivacyManager::class)
            );
        });
        $this->app->alias(LogOperationsManager::class, 'logoperations');
        $this->app->alias(LogOperationsManager::class, 'log-operations');

        // Registrazione dei servizi Zero-Code Tracking Studio
        $this->app->singleton(\SalvatoreCervone\LogOperations\Services\RuleEngine::class);
        $this->app->singleton(\SalvatoreCervone\LogOperations\Services\AppScanner::class);
        $this->app->singleton(\SalvatoreCervone\LogOperations\Services\QueueAlertService::class);
        $this->app->singleton(\SalvatoreCervone\LogOperations\Services\MethodInterceptor::class, function ($app) {
            return new \SalvatoreCervone\LogOperations\Services\MethodInterceptor(
                $app,
                $app->make(\SalvatoreCervone\LogOperations\Services\RuleEngine::class)
            );
        });
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        // Pubblicazione del file di configurazione
        $this->publishes([
            __DIR__ . '/../config/logoperations.php' => config_path('logoperations.php'),
        ], 'logoperations-config');

        // Pubblicazione delle migrazioni
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'logoperations-migrations');

        // Caricamento automatico delle migrazioni
        $this->loadMigrationsFrom(
            __DIR__ . '/../database/migrations'
        );

        // Caricamento delle rotte API
        $this->loadRoutesFrom(
            __DIR__ . '/../routes/api.php'
        );

        // Pubblicazione dei componenti Vue
        $this->publishes([
            __DIR__ . '/../resources/js/' => resource_path('js/vendor/logoperations'),
        ], 'logoperations-vue');

        // Registrazione alias del middleware
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('log.operations', LogOperationsMiddleware::class);
        $router->aliasMiddleware('logoperations', LogOperationsMiddleware::class);

        // Registrazione comandi console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \SalvatoreCervone\LogOperations\Console\Commands\ForgetUserCommand::class,
            ]);
        }

        // Attivazione dei proxy per i metodi/servizi tracciati dinamicamente
        try {
            $this->app->make(\SalvatoreCervone\LogOperations\Services\MethodInterceptor::class)->registerActiveInterceptors();
        } catch (\Throwable $e) {
            // Ignora se il database o le tabelle non sono ancora pronte durante migrazioni iniziali
        }
    }
}
