<?php

namespace Tests\Acceptance;

use Core\Database\Database;
use Core\Env\EnvLoader;
use RuntimeException;
use Tests\Support\AcceptanceTester;

class BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        EnvLoader::init();
        $this->assertTesting();
        Database::drop();
        Database::create();
        Database::migrate();
    }

    public function _after(AcceptanceTester $page): void
    {
        $this->assertTesting();
        Database::drop();
    }

    private function assertTesting(): void
    {
        if (($_ENV['DB_DATABASE'] ?? '') !== 'encurtador_test' || ($_ENV['APP_ENV'] ?? '') !== 'testing') {
            throw new RuntimeException('A aceitação exige APP_ENV=testing e o banco encurtador_test.');
        }
    }
}
