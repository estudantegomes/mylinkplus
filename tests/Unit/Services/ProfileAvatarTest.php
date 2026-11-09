<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\ProfileAvatar;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\ProfileFixtures;

class ProfileAvatarTest extends TestCase
{
    private string $root;
    private ProfileAvatar $service;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/profile-avatar-testing-' . bin2hex(random_bytes(8));
        $this->service = new ProfileAvatar($this->root);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->root . '/1/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($this->root . '/1')) {
            rmdir($this->root . '/1');
        }
        if (is_dir($this->root)) {
            rmdir($this->root);
        }
    }

    public function test_valid_formats_are_decoded_cropped_and_reencoded_with_random_names(): void
    {
        $keys = [];
        foreach (['avatar.png', 'avatar.jpg', 'avatar.webp'] as $name) {
            $stored = $this->service->storeImage(ProfileFixtures::file($name), 1);
            $path = $this->root . '/1/' . $stored['storage_key'];
            $this->assertFileExists($path);
            $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\.webp\z/', $stored['storage_key']);
            $dimensions = getimagesize($path);
            $this->assertIsArray($dimensions);
            $this->assertSame('image/webp', $dimensions['mime']);
            $this->assertSame(512, $stored['width']);
            $this->assertSame(512, $stored['height']);
            $keys[] = $stored['storage_key'];
        }
        $this->assertCount(3, array_unique($keys));
    }

    public function test_svg_and_executable_disguised_as_png_are_rejected(): void
    {
        $fake = tempnam(sys_get_temp_dir(), 'avatar-fake-');
        $this->assertIsString($fake);
        file_put_contents($fake, '<?php echo "arquivo de teste";');
        try {
            foreach (
                [ProfileFixtures::file('blocked.svg'), [
                'error' => UPLOAD_ERR_OK, 'tmp_name' => $fake, 'name' => 'foto.png', 'type' => 'image/png',
                ]] as $file
            ) {
                try {
                    $this->service->validate($file);
                    $this->fail('O arquivo deveria ser rejeitado.');
                } catch (InvalidArgumentException $exception) {
                    $this->assertStringContainsString('Formato inválido', $exception->getMessage());
                }
            }
        } finally {
            unlink($fake);
        }
    }

    public function test_actual_size_is_checked_even_if_client_reports_one_byte(): void
    {
        $fake = tempnam(sys_get_temp_dir(), 'avatar-large-');
        $this->assertIsString($fake);
        file_put_contents($fake, str_repeat('x', ProfileAvatar::MAX_BYTES + 1));
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('2 MB');
            $this->service->validate(['error' => 0, 'tmp_name' => $fake, 'name' => 'foto.png', 'size' => 1]);
        } finally {
            unlink($fake);
        }
    }

    public function test_image_dimensions_are_limited_before_decoding(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'avatar-dimensions-');
        $this->assertIsString($file);
        $image = imagecreatetruecolor(4097, 1);
        $this->assertInstanceOf(\GdImage::class, $image);
        imagepng($image, $file);
        imagedestroy($image);
        try {
            $this->expectException(InvalidArgumentException::class);
            $this->expectExceptionMessage('4096');
            $this->service->validate(['error' => 0, 'tmp_name' => $file, 'name' => 'foto.png']);
        } finally {
            unlink($file);
        }
    }

    public function test_reencoding_does_not_preserve_code_appended_to_an_image(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'avatar-polyglot-');
        $this->assertIsString($file);
        $source = file_get_contents(ProfileFixtures::file()['tmp_name']);
        file_put_contents($file, $source . '<?php echo "remover-este-conteudo";');
        try {
            $stored = $this->service->storeImage(['error' => 0, 'tmp_name' => $file, 'name' => 'foto.png'], 1);
            $result = file_get_contents($this->root . '/1/' . $stored['storage_key']);
            $this->assertIsString($result);
            $this->assertStringNotContainsString('<?php', $result);
            $this->assertStringNotContainsString('remover-este-conteudo', $result);
        } finally {
            unlink($file);
        }
    }

    public function test_missing_file_and_php_upload_errors_are_reported(): void
    {
        $files = [null, ['error' => UPLOAD_ERR_NO_FILE], ['error' => UPLOAD_ERR_INI_SIZE], ['error' => UPLOAD_ERR_PARTIAL]];
        foreach ($files as $file) {
            try {
                $this->service->validate($file);
                $this->fail('O envio inválido deveria ser rejeitado.');
            } catch (InvalidArgumentException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function test_upload_requires_an_actual_http_upload(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('formulário');
        $this->service->upload(new User(['id' => 1]), ProfileFixtures::file());
    }

    public function test_testing_uploads_do_not_use_the_production_storage_directory(): void
    {
        $production = getenv('PROFILE_STORAGE_PATH');
        $testing = getenv('PROFILE_TEST_STORAGE_PATH');
        putenv('PROFILE_STORAGE_PATH=' . $this->root . '-production');
        putenv('PROFILE_TEST_STORAGE_PATH=' . $this->root);
        try {
            $stored = (new ProfileAvatar())->storeImage(ProfileFixtures::file(), 1);
            $this->assertFileExists($this->root . '/1/' . $stored['storage_key']);
            $this->assertDirectoryDoesNotExist($this->root . '-production');
        } finally {
            putenv($production === false ? 'PROFILE_STORAGE_PATH' : 'PROFILE_STORAGE_PATH=' . $production);
            putenv($testing === false ? 'PROFILE_TEST_STORAGE_PATH' : 'PROFILE_TEST_STORAGE_PATH=' . $testing);
        }
    }
}
