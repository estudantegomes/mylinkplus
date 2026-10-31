<?php

namespace Tests\Integration\Controllers;

use App\Controllers\HomeController;

class HomeControllerTest extends ControllerTestCase
{
    public function test_render_login_page(): void
    {
        $_SESSION = [];

        $response = $this->get(
            action: 'login',
            controllerName: HomeController::class
        );

        $this->assertStringContainsString(
            'id="mylink-login-form"',
            $response
        );

        $this->assertStringContainsString(
            'name="csrf_token"',
            $response
        );

        $this->assertNotEmpty($_SESSION['csrf_token']);
    }
}
