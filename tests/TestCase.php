<?php

namespace Tests;

use Core\Database\Database;
use PHPUnit\Framework\TestCase as FrameworkTestCase;

class TestCase extends FrameworkTestCase
{
    public function setUp(): void
    {
        $this->assertTestDatabase();

        Database::drop();
        Database::create();
        Database::migrate();
    }

    public function tearDown(): void
    {
        $this->assertTestDatabase();

        Database::drop();
    }

    private function assertTestDatabase(): void
    {
        if (($_ENV['DB_DATABASE'] ?? '') !== 'encurtador_test') {
            throw new \RuntimeException(
                'Use exclusivamente o banco encurtador_test nos testes.'
            );
        }
    }

    protected function getOutput(callable $callable): string
    {
        ob_start();
        $callable();
        return ob_get_clean();
    }
}
