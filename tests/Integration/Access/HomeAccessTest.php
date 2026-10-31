<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class HomeAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = new Client([
            'allow_redirects' => false,
            'base_uri' => 'http://web:8080',
        ]);
    }

    public function test_should_access_home_route(): void
    {
        $response = $this->client->get('/');

        $this->assertEquals(200, $response->getStatusCode());

        $this->assertStringContainsString(
            'id="mylink-login-form"',
            (string) $response->getBody()
        );
    }

    public function test_guest_cannot_access_home(): void
    {
        $response = $this->client->get('/home');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            '/login',
            $response->getHeaderLine('Location')
        );
    }

    public function test_guest_cannot_access_admin(): void
    {
        $response = $this->client->get('/admin');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            '/login',
            $response->getHeaderLine('Location')
        );
    }
}
