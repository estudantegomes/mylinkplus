<?php

namespace Tests\Unit\Lib;

use Core\Exceptions\HTTPException;
use Core\Http\Request;
use Lib\Csrf;
use PHPUnit\Framework\TestCase;

class CsrfTest extends TestCase
{
    public function setUp(): void
    {
        $_SESSION = [];
        $_REQUEST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/tags';
    }

    public function tearDown(): void
    {
        $_SESSION = [];
        $_REQUEST = [];
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    }

    public function test_token_is_stable_and_a_valid_form_is_accepted(): void
    {
        $token = Csrf::token();

        $this->assertSame(64, strlen($token));
        $this->assertSame($token, Csrf::token());

        $request = new Request();
        $request->addParams(['csrf_token' => $token]);

        Csrf::validate($request);
    }

    public function test_missing_forged_or_array_tokens_are_rejected(): void
    {
        Csrf::token();

        foreach (['', 'token-inventado', ['token']] as $token) {
            $request = new Request();
            $request->addParams(['csrf_token' => $token]);

            try {
                Csrf::validate($request);
                $this->fail('O formulário deveria ser rejeitado.');
            } catch (HTTPException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
    }
}
