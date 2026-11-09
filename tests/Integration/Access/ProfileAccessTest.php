<?php

namespace Tests\Integration\Access;

use App\Models\ProfilePhoto;
use App\Services\ProfileAvatar;
use Core\Database\Database;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Tests\Support\Fixtures;
use Tests\Support\ProfileFixtures;
use Tests\TestCase;

class ProfileAccessTest extends TestCase
{
    private Client $client;

    public function setUp(): void
    {
        parent::setUp();
        $this->client = new Client([
            'base_uri' => getenv('TEST_BASE_URI') ?: 'http://web:8080', 'cookies' => true,
            'allow_redirects' => false, 'http_errors' => false,
        ]);
    }

    public function tearDown(): void
    {
        ProfileFixtures::clearFiles();
        parent::tearDown();
    }

    public function test_all_profile_routes_require_authentication(): void
    {
        foreach (
            [['GET', '/profile'], ['POST', '/profile/photos'], ['GET', '/profile/photos/1/image'],
            ['POST', '/profile/photos/1/current'], ['DELETE', '/profile/photos/1']] as [$method, $uri]
        ) {
            $response = $this->client->request($method, $uri);
            $this->assertSame(302, $response->getStatusCode());
            $this->assertSame('/login', $response->getHeaderLine('Location'));
        }
    }

    public function test_real_upload_display_selection_and_removal_preserve_owner_and_files(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outra-conta@example.com');
        $token = $this->login($owner->email);
        $response = $this->upload('avatar.png', $token, ['user_id' => $other->id]);
        $this->assertSame(302, $response->getStatusCode());
        $first = ProfilePhoto::findBy(['user_id' => $owner->id]);
        $this->assertNotNull($first);
        $this->assertSame([], ProfilePhoto::where(['user_id' => $other->id]));
        $service = new ProfileAvatar();
        $firstPath = $service->filePath($first);
        $this->assertFileExists($firstPath);
        $image = $this->client->get('/profile/photos/' . $first->id . '/image');
        $this->assertSame(200, $image->getStatusCode());
        $this->assertSame('image/webp', $image->getHeaderLine('Content-Type'));
        $this->assertSame('nosniff', $image->getHeaderLine('X-Content-Type-Options'));
        $this->assertSame('private, no-store', $image->getHeaderLine('Cache-Control'));
        $dimensions = getimagesizefromstring((string) $image->getBody());
        $this->assertIsArray($dimensions);
        $this->assertSame('image/webp', $dimensions['mime']);

        $this->assertSame(302, $this->upload('avatar.jpg', $token)->getStatusCode());
        $second = ProfilePhoto::findBy(['user_id' => $owner->id, 'is_current' => true]);
        $this->assertNotNull($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertCount(2, $owner->profilePhotos()->get());
        $this->assertFalse(ProfilePhoto::findById($first->id)?->is_current);
        $home = (string) $this->client->get('/home')->getBody();
        $this->assertStringContainsString('id="profile-menu"', $home);
        $this->assertStringContainsString('/profile/photos/' . $second->id . '/image', $home);

        $select = $this->client->post('/profile/photos/' . $first->id . '/current', [
            'form_params' => ['csrf_token' => $token],
        ]);
        $this->assertSame(302, $select->getStatusCode());
        $this->assertSame($first->id, $owner->currentProfilePhoto()?->id);
        $delete = $this->client->post('/profile/photos/' . $first->id, [
            'form_params' => ['_method' => 'DELETE', 'csrf_token' => $token],
        ]);
        $this->assertSame(302, $delete->getStatusCode());
        $this->assertNull(ProfilePhoto::findById($first->id));
        $this->assertFileDoesNotExist($firstPath);
        $this->assertNull($owner->currentProfilePhoto());
        $this->assertFileExists($service->filePath($second));
        $this->assertSame(404, $this->client->get('/profile/photos/' . $first->id . '/image')->getStatusCode());
    }

    public function test_another_users_photos_cannot_be_listed_viewed_selected_or_deleted(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outra-conta@example.com');
        $foreign = ProfileFixtures::photo($other, true);
        $token = $this->login($owner->email);
        $profile = (string) $this->client->get('/profile?user_id=' . $other->id)->getBody();
        $this->assertStringNotContainsString('data-photo-id="' . $foreign->id . '"', $profile);
        $responses = [
            $this->client->get('/profile/photos/' . $foreign->id . '/image'),
            $this->client->post('/profile/photos/' . $foreign->id . '/current', ['form_params' => ['csrf_token' => $token]]),
            $this->client->post('/profile/photos/' . $foreign->id, [
                'form_params' => ['_method' => 'DELETE', 'csrf_token' => $token],
            ]),
            $this->client->get('/profile/photos/999999/image'),
            $this->client->get('/profile/photos/2147483648/image'),
        ];
        foreach ($responses as $response) {
            $this->assertSame(404, $response->getStatusCode());
            $this->assertStringNotContainsString('Stack Trace', (string) $response->getBody());
            $this->assertStringNotContainsString($foreign->storage_key, (string) $response->getBody());
        }
        $this->assertNotNull(ProfilePhoto::findById($foreign->id));
        $this->assertFileExists((new ProfileAvatar())->filePath($foreign));
    }

    public function test_invalid_uploads_are_rejected_without_saving_files_or_records(): void
    {
        $owner = Fixtures::user();
        $token = $this->login($owner->email);
        $missing = $this->client->post('/profile/photos', ['form_params' => ['csrf_token' => $token]]);
        $this->assertSame(422, $missing->getStatusCode());
        $this->assertSame(422, $this->upload('blocked.svg', $token)->getStatusCode());
        $fake = $this->client->post('/profile/photos', ['multipart' => [
            ['name' => 'csrf_token', 'contents' => $token],
            ['name' => 'photo', 'contents' => '<?php echo "teste";', 'filename' => 'foto.png',
                'headers' => ['Content-Type' => 'image/png']],
        ]]);
        $this->assertSame(422, $fake->getStatusCode());
        $large = $this->client->post('/profile/photos', ['multipart' => [
            ['name' => 'csrf_token', 'contents' => $token],
            ['name' => 'photo', 'contents' => str_repeat('x', ProfileAvatar::MAX_BYTES + 1), 'filename' => 'foto.png'],
        ]]);
        $this->assertSame(422, $large->getStatusCode());
        $this->assertStringContainsString('2 MB', (string) $large->getBody());
        $this->assertSame([], ProfilePhoto::all());
        $this->assertSame([], glob((new ProfileAvatar())->rootPath() . '/' . $owner->id . '/*.webp'));
    }

    public function test_write_routes_reject_missing_csrf_even_for_authenticated_users(): void
    {
        $owner = Fixtures::user();
        $photo = ProfileFixtures::photo($owner, true);
        $this->login($owner->email);
        foreach (
            [
            $this->upload('avatar.png', ''),
            $this->client->post('/profile/photos/' . $photo->id . '/current'),
            $this->client->post('/profile/photos/' . $photo->id, ['form_params' => ['_method' => 'DELETE']]),
            ] as $response
        ) {
            $this->assertSame(403, $response->getStatusCode());
        }
        $this->assertCount(1, $owner->profilePhotos()->get());
        $this->assertFileExists((new ProfileAvatar())->filePath($photo));
    }

    public function test_gallery_quota_is_enforced_in_backend(): void
    {
        $owner = Fixtures::user();
        for ($index = 0; $index < ProfileAvatar::MAX_PHOTOS; $index++) {
            ProfileFixtures::photo($owner);
        }
        $token = $this->login($owner->email);
        $response = $this->upload('avatar.png', $token);
        $this->assertSame(422, $response->getStatusCode());
        $this->assertStringContainsString('até 10 fotos', (string) $response->getBody());
        $this->assertCount(10, $owner->profilePhotos()->get());
    }

    public function test_failed_database_insert_cleans_up_the_processed_file(): void
    {
        $owner = Fixtures::user();
        $token = $this->login($owner->email);
        Database::exec(<<<'SQL'
            CREATE FUNCTION reject_photo_insert() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Inserção bloqueada pelo teste'; END $$;
            CREATE TRIGGER reject_photo_insert BEFORE INSERT ON profile_photos
                FOR EACH ROW EXECUTE FUNCTION reject_photo_insert();
            SQL);
        $response = $this->upload('avatar.png', $token);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame([], ProfilePhoto::all());
        $this->assertSame([], glob((new ProfileAvatar())->rootPath() . '/' . $owner->id . '/*.webp'));
    }

    /** @param array<string, int|string> $extra */
    private function upload(string $name, string $token, array $extra = []): ResponseInterface
    {
        $stream = fopen(ProfileFixtures::file($name)['tmp_name'], 'rb');
        $this->assertIsResource($stream);
        $multipart = [['name' => 'csrf_token', 'contents' => $token],
            ['name' => 'photo', 'contents' => $stream, 'filename' => $name]];
        foreach ($extra as $field => $value) {
            $multipart[] = ['name' => $field, 'contents' => (string) $value];
        }
        try {
            return $this->client->post('/profile/photos', ['multipart' => $multipart]);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function login(string $email): string
    {
        $response = $this->client->get('/login');
        $token = $this->token($response);
        $login = $this->client->post('/login', ['form_params' => [
            'email' => $email, 'password' => 'Senha@12345', 'csrf_token' => $token,
        ]]);
        $this->assertSame(302, $login->getStatusCode());

        return $this->token($this->client->get('/profile'));
    }

    private function token(ResponseInterface $response): string
    {
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, preg_match('/name="csrf_token"\s+value="([a-f0-9]{64})"/', (string) $response->getBody(), $matches));

        return $matches[1];
    }
}
