<?php

namespace Tests\Integration\Controllers;

use App\Controllers\FolderController;
use App\Controllers\RegistrationController;
use App\Controllers\TagController;
use App\Models\Folder;
use App\Models\Tag;
use App\Models\User;
use Core\Exceptions\HTTPException;
use Tests\Support\Fixtures;

class OrganizationSecurityTest extends ControllerTestCase
{
    public function tearDown(): void
    {
        $_SESSION = [];
        http_response_code(200);
        parent::tearDown();
    }

    public function test_other_users_records_cannot_be_read_changed_or_deleted(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outro@example.com');

        $_SESSION = ['user' => ['id' => $owner->id], 'csrf_token' => 'token'];

        foreach ([FolderController::class => Folder::class, TagController::class => Tag::class] as $controller => $class) {
            $item = new $class(['user_id' => $other->id, 'name' => 'Privado']);

            $this->assertTrue($item->save());

            foreach (['show', 'edit', 'update', 'destroy'] as $action) {
                try {
                    $this->post($action, $controller, ['id' => $item->id, 'name' => 'Alterado', 'csrf_token' => 'token']);
                    $this->fail('O acesso a um registro de outra conta deveria ser bloqueado.');
                } catch (HTTPException $exception) {
                    $this->assertSame(404, $exception->getStatusCode());
                }

                $this->assertSame('Privado', $class::findById($item->id)?->name);
            }
        }
    }

    public function test_registration_ignores_admin_role_and_stores_a_password_hash(): void
    {
        $_SESSION = ['csrf_token' => 'token'];

        $response = $this->post('create', RegistrationController::class, [
            'name' => 'Novo usuário',
            'email' => 'novo@example.com',
            'password' => 'Senha@12345',
            'password_confirmation' => 'Senha@12345',
            'csrf_token' => 'token',
            'role' => 'admin',
            'is_active' => false,
        ]);

        $this->assertStringContainsString('Location: /login', $response);

        $user = User::findByEmail('novo@example.com');

        $this->assertNotNull($user);
        $this->assertSame('user', $user->__get('role'));
        $this->assertTrue($user->isActive());
        $this->assertTrue($user->authenticate('Senha@12345'));
        $this->assertNotSame('Senha@12345', $user->password_hash);
    }

    public function test_invalid_registration_does_not_save_a_user(): void
    {
        $_SESSION = ['csrf_token' => 'token'];

        $response = $this->post('create', RegistrationController::class, [
            'name' => ' ',
            'email' => 'invalido',
            'password' => '123',
            'password_confirmation' => '456',
            'csrf_token' => 'token',
        ]);

        $this->assertSame(422, http_response_code());
        $this->assertStringContainsString('Use pelo menos 8 caracteres', $response);
        $this->assertCount(0, User::all());
    }

    public function test_write_without_csrf_token_is_rejected(): void
    {
        $owner = Fixtures::user();

        $_SESSION = ['user' => ['id' => $owner->id], 'csrf_token' => 'token'];

        $this->expectException(HTTPException::class);

        $this->post('create', TagController::class, ['name' => 'Sem token']);
    }
}
