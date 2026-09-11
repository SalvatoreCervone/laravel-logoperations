<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use SalvatoreCervone\LogOperations\Tests\TestCase;

class MigrationConnectionTest extends TestCase
{
    public function test_all_migrations_return_configured_database_connection(): void
    {
        $migrationFiles = [
            __DIR__ . '/../../database/migrations/2026_01_01_000000_create_log_operazioni_table.php',
            __DIR__ . '/../../database/migrations/2026_01_01_000001_upgrade_logoperazionis_table.php',
            __DIR__ . '/../../database/migrations/2026_01_01_000002_create_log_operazioni_regole_table.php',
            __DIR__ . '/../../database/migrations/2026_01_01_000003_add_subject_to_log_operazioni_table.php',
            __DIR__ . '/../../database/migrations/2026_01_01_000004_add_pagination_index_to_log_operazioni_table.php',
            __DIR__ . '/../../database/migrations/2026_01_01_000005_widen_controllermethod_column.php',
            __DIR__ . '/../../database/migrations/2026_01_01_000006_create_log_operazioni_soggetti_table.php',
        ];

        // Default: null
        config(['logoperations.database_connection' => null]);

        foreach ($migrationFiles as $file) {
            $this->assertFileExists($file);
            $migration = require $file;

            $this->assertTrue(method_exists($migration, 'getConnection'), "Migration in $file must define getConnection()");
            $this->assertNull($migration->getConnection());
        }

        // Custom connection
        config(['logoperations.database_connection' => 'dedicated_logs_db']);

        foreach ($migrationFiles as $file) {
            $migration = require $file;
            $this->assertEquals('dedicated_logs_db', $migration->getConnection());
        }
    }

    public function test_ignore_migrations_flag_toggles_correctly(): void
    {
        $this->assertTrue(\SalvatoreCervone\LogOperations\LogOperationsManager::$runsMigrations);

        \SalvatoreCervone\LogOperations\LogOperationsManager::ignoreMigrations();
        $this->assertFalse(\SalvatoreCervone\LogOperations\LogOperationsManager::$runsMigrations);

        // Ripristina per gli altri test
        \SalvatoreCervone\LogOperations\LogOperationsManager::$runsMigrations = true;
    }

    public function test_load_migrations_config_is_true_by_default(): void
    {
        $this->assertTrue(config('logoperations.load_migrations', true));
    }
}
