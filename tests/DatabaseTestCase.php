<?php

namespace Tests;

use App\Database;
use PHPUnit\Framework\TestCase;

abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        Database::get()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $pdo = Database::get();

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}
