<?php

namespace Tests\Integration\Access;

use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

class OrganizationAccessTest extends TestCase
{
    public function test_all_crud_routes_require_authentication(): void
    {
        $client = new Client(['base_uri' => 'http://web:8080', 'allow_redirects' => false, 'http_errors' => false]);

        foreach (['folders', 'tags'] as $resource) {
            $routes = [
                ['GET', '/' . $resource],
                ['GET', '/' . $resource . '/new'],
                ['POST', '/' . $resource],
                ['GET', '/' . $resource . '/1'],
                ['GET', '/' . $resource . '/1/edit'],
                ['PUT', '/' . $resource . '/1'],
                ['DELETE', '/' . $resource . '/1'],
            ];

            foreach ($routes as [$method, $uri]) {
                $response = $client->request($method, $uri);

                $this->assertSame(302, $response->getStatusCode(), $method . ' ' . $uri);
                $this->assertSame('/login', $response->getHeaderLine('Location'));
            }
        }
    }
}
