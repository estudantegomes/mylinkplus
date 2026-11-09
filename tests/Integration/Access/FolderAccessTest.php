<?php

namespace Tests\Integration\Access;

use App\Models\Folder;
use App\Models\User;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\Fixtures;
use Tests\TestCase;

class FolderAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->client = new Client([
            'base_uri' => 'http://web:8080',
            'cookies' => true,
            'allow_redirects' => false,
            'http_errors' => false,
        ]);
    }

    public function test_listing_and_parent_selection_do_not_expose_other_users_folders(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outra-conta@example.com');
        $root = $this->folder($owner, 'Minha pasta raiz');
        $this->folder($owner, 'Minha subpasta', $root->id);
        $foreignRoot = $this->folder($other, 'Pasta privada de outra conta');
        $foreignChild = $this->folder($other, 'Subpasta privada de outra conta', $foreignRoot->id);

        $this->login($owner);

        $response = $this->client->get('/folders?user_id=' . $other->id);

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        $this->assertStringContainsString('Minha pasta raiz', $html);
        $this->assertStringNotContainsString('Minha subpasta', $html);
        $this->assertStringNotContainsString($foreignRoot->name, $html);
        $this->assertStringNotContainsString($foreignChild->name, $html);

        $response = $this->client->get('/folders?parent_id=' . $root->id . '&user_id=' . $other->id);

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        $this->assertStringContainsString('Minha subpasta', $html);
        $this->assertStringNotContainsString($foreignRoot->name, $html);
        $this->assertStringNotContainsString($foreignChild->name, $html);

        $response = $this->client->get('/folders/new');

        $this->assertSame(200, $response->getStatusCode());
        $html = (string) $response->getBody();
        $this->assertStringContainsString('Minha pasta raiz', $html);
        $this->assertStringNotContainsString($foreignRoot->name, $html);
        $this->assertStringNotContainsString($foreignChild->name, $html);

        $blockedUris = [
            '/folders?parent_id=' . $foreignRoot->id,
            '/folders?parent_id=' . $foreignChild->id,
            '/folders/new?parent_id=' . $foreignRoot->id,
            '/folders/' . $foreignRoot->id,
            '/folders/' . $foreignChild->id,
            '/folders?parent_id=999999',
            '/folders?parent_id=2147483648',
            '/folders/2147483648',
        ];

        foreach ($blockedUris as $uri) {
            $response = $this->client->get($uri);

            $this->assertSame(404, $response->getStatusCode(), $uri);
            $html = (string) $response->getBody();
            $this->assertStringNotContainsString($foreignRoot->name, $html);
            $this->assertStringNotContainsString($foreignChild->name, $html);
            $this->assertStringNotContainsString('Stack Trace', $html);
        }
    }

    public function test_creation_uses_authenticated_owner_even_if_request_sends_another_user_id(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outra-conta@example.com');

        $this->login($owner);
        $token = $this->csrfToken($this->client->get('/folders/new'));

        $response = $this->client->post('/folders', ['form_params' => [
            'name' => 'Raiz criada',
            'parent_id' => '',
            'user_id' => $other->id,
            'csrf_token' => $token,
        ]]);

        $root = Folder::findBy(['name' => 'Raiz criada']);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotNull($root);
        $this->assertSame('/folders/' . $root->id, $response->getHeaderLine('Location'));
        $this->assertSame($owner->id, $root->user_id);
        $this->assertNull($root->parent_id);

        $response = $this->client->post('/folders', ['form_params' => [
            'name' => 'Subpasta criada',
            'parent_id' => $root->id,
            'user_id' => $other->id,
            'csrf_token' => $token,
        ]]);

        $child = Folder::findBy(['name' => 'Subpasta criada']);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertNotNull($child);
        $this->assertSame('/folders/' . $child->id, $response->getHeaderLine('Location'));
        $this->assertSame($owner->id, $child->user_id);
        $this->assertSame($root->id, $child->parent_id);
        $this->assertSame([], Folder::where(['user_id' => $other->id]));
    }

    public function test_creation_and_move_reject_unavailable_parents_and_invalid_csrf(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outra-conta@example.com');
        $root = $this->folder($owner, 'Minha pasta');
        $child = $this->folder($owner, 'Minha subpasta', $root->id);
        $foreignRoot = $this->folder($other, 'Pasta privada de outra conta');

        $this->login($owner);
        $token = $this->csrfToken($this->client->get('/folders/new'));

        foreach ([$foreignRoot->id, '999999', '2147483648', '0', ['1']] as $parentId) {
            $response = $this->client->post('/folders', ['form_params' => [
                'name' => 'Subpasta bloqueada',
                'parent_id' => $parentId,
                'csrf_token' => $token,
            ]]);

            $this->assertSame(422, $response->getStatusCode());
            $this->assertMatchesRegularExpression('/data-error="parent_id">[^<]+<\/p>/', (string) $response->getBody());
        }

        $response = $this->client->post('/folders/' . $child->id, ['form_params' => [
            '_method' => 'PUT',
            'name' => $child->name,
            'parent_id' => $foreignRoot->id,
            'csrf_token' => $token,
        ]]);

        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame($root->id, Folder::findById($child->id)?->parent_id);

        $response = $this->client->post('/folders', ['form_params' => [
            'name' => 'Subpasta bloqueada',
            'parent_id' => $root->id,
        ]]);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertNull(Folder::findBy(['name' => 'Subpasta bloqueada']));
    }

    private function folder(User $owner, string $name, ?int $parentId = null): Folder
    {
        $folder = new Folder(['user_id' => $owner->id, 'name' => $name, 'parent_id' => $parentId]);

        $this->assertTrue($folder->save());

        return $folder;
    }

    private function login(User $user): void
    {
        $token = $this->csrfToken($this->client->get('/login'));
        $response = $this->client->post('/login', ['form_params' => [
            'email' => $user->email,
            'password' => 'Senha@12345',
            'csrf_token' => $token,
        ]]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/home', $response->getHeaderLine('Location'));
    }

    private function csrfToken(ResponseInterface $response): string
    {
        $this->assertSame(200, $response->getStatusCode());

        $this->assertSame(1, preg_match(
            '/name="csrf_token"\s+value="([a-f0-9]{64})"/',
            (string) $response->getBody(),
            $matches
        ));

        return $matches[1];
    }
}
