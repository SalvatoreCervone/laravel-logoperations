<?php

namespace SalvatoreCervone\LogOperations\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use SalvatoreCervone\LogOperations\LogOperationsServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends OrchestraTestCase
{
    use RefreshDatabase;

    /**
     * Registra i ServiceProvider del pacchetto.
     */
    protected function getPackageProviders($app)
    {
        return [
            LogOperationsServiceProvider::class,
        ];
    }

    /**
     * Configurazione dell'ambiente di test.
     */
    protected function getEnvironmentSetUp($app)
    {
        // Database SQLite in memoria
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('logoperations.enabled', true);
        $app['config']->set('logoperations.mode', 'all');
        $app['config']->set('logoperations.allow_in_local', true);
    }

    /**
     * Carica ed esegue le migrazioni del pacchetto.
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
