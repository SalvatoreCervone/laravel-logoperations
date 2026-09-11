<?php

namespace SalvatoreCervone\LogOperations\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use SalvatoreCervone\LogOperations\Models\OperationLog;
use SalvatoreCervone\LogOperations\Models\OperationSubject;
use SalvatoreCervone\LogOperations\Tests\TestCase;

class CrossDbTestUser extends Model
{
    protected $table = 'cross_db_users';
    protected $guarded = [];
}

class CrossDatabaseRelationTest extends TestCase
{
    public function test_morph_relations_do_not_inherit_logs_database_connection(): void
    {
        // Simula che il package sia configurato su una connessione DB dedicata
        config([
            'database.default' => 'testing',
            'database.connections.dedicated_logs_db' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'logoperations.database_connection' => 'dedicated_logs_db',
        ]);

        $log = new OperationLog();
        $this->assertEquals('dedicated_logs_db', $log->getConnectionName());

        // Istanzia la relazione user()
        $userRelation = $log->user();
        $userModel = $userRelation->createModelByType(CrossDbTestUser::class);

        // Il modello User NON deve ereditare 'dedicated_logs_db', ma deve usare il default dell'applicazione
        $this->assertEquals('testing', $userModel->getConnectionName());
        $this->assertNotEquals('dedicated_logs_db', $userModel->getConnectionName());
    }

    public function test_subject_morph_relations_do_not_inherit_logs_database_connection(): void
    {
        config([
            'database.default' => 'testing',
            'database.connections.dedicated_logs_db' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
            'logoperations.database_connection' => 'dedicated_logs_db',
        ]);

        $subject = new OperationSubject();
        $this->assertEquals('dedicated_logs_db', $subject->getConnectionName());

        $subjectRelation = $subject->subject();
        $subjectModel = $subjectRelation->createModelByType(CrossDbTestUser::class);

        $this->assertEquals('testing', $subjectModel->getConnectionName());
        $this->assertNotEquals('dedicated_logs_db', $subjectModel->getConnectionName());
    }
}
