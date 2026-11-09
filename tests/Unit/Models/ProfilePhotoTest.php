<?php

namespace Tests\Unit\Models;

use App\Models\ProfilePhoto;
use App\Models\User;
use App\Services\ProfileAvatar;
use Core\Database\Database;
use PDOException;
use Tests\Support\Fixtures;
use Tests\Support\ProfileFixtures;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    public function tearDown(): void
    {
        ProfileFixtures::clearFiles();
        parent::tearDown();
    }

    public function test_has_many_and_belongs_to_connect_user_to_multiple_photos(): void
    {
        $owner = Fixtures::user();
        $first = ProfileFixtures::photo($owner, true);
        $second = ProfileFixtures::photo($owner);
        $photos = $owner->profilePhotos()->get();
        $this->assertCount(2, $photos);
        $this->assertSame($owner->id, $first->user()->get()->id);
        $this->assertSame($second->id, $owner->profilePhotos()->findById($second->id)?->id);
        $this->assertSame($first->id, $owner->currentProfilePhoto()?->id);
        $this->assertFalse(ProfilePhoto::findById($second->id)?->is_current);

        $other = Fixtures::user('outro@example.com');
        $this->assertNull($other->profilePhotos()->findById($first->id));
    }

    public function test_select_and_remove_update_database_and_filesystem(): void
    {
        $owner = Fixtures::user();
        $first = ProfileFixtures::photo($owner, true);
        $second = ProfileFixtures::photo($owner);
        $service = new ProfileAvatar();
        $service->select($owner, $second);
        $this->assertSame($second->id, $owner->currentProfilePhoto()?->id);
        $this->assertFalse(ProfilePhoto::findById($first->id)?->is_current);
        $path = $service->filePath($second);
        $this->assertFileExists($path);
        $service->remove($owner, $second);
        $this->assertFileDoesNotExist($path);
        $this->assertNull(ProfilePhoto::findById($second->id));
        $this->assertNull($owner->currentProfilePhoto());
        $this->assertCount(1, $owner->profilePhotos()->get());
    }

    public function test_parent_deletion_is_restricted_and_keeps_files_until_gallery_is_empty(): void
    {
        $owner = Fixtures::user();
        $photo = ProfileFixtures::photo($owner);
        $service = new ProfileAvatar();
        try {
            $owner->destroy();
            $this->fail('A FK deve impedir a exclusão de um usuário com fotos.');
        } catch (PDOException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }
        $this->assertNotNull(User::findById($owner->id));
        $this->assertFileExists($service->filePath($photo));
        $service->remove($owner, $photo);
        $this->assertTrue($owner->destroy());
    }

    public function test_database_rejects_a_photo_for_a_nonexistent_gallery_owner(): void
    {
        $statement = Database::getDatabaseConn()->prepare(
            'INSERT INTO profile_photos (user_id, original_name, storage_key, mime_type, size_bytes, width, height)'
            . ' VALUES (999999, :name, :key, :mime, 1, 1, 1)'
        );
        try {
            $statement->execute(['name' => 'foto.png', 'key' => bin2hex(random_bytes(32)) . '.webp', 'mime' => 'image/webp']);
            $this->fail('A FK deve rejeitar um proprietário inexistente.');
        } catch (PDOException $exception) {
            $this->assertSame('23503', $exception->getCode());
        }
        $this->assertSame([], ProfilePhoto::all());
    }

    public function test_database_allows_at_most_one_current_photo_per_user(): void
    {
        $owner = Fixtures::user();
        ProfileFixtures::photo($owner, true);
        try {
            ProfileFixtures::photo($owner, true);
            $this->fail('Duas fotos atuais não podem ser gravadas.');
        } catch (PDOException $exception) {
            $this->assertSame('23505', $exception->getCode());
        }
    }

    public function test_metadata_validation_rejects_invalid_owner_path_type_size_and_dimensions(): void
    {
        $photo = new ProfilePhoto([
            'user_id' => 999999, 'original_name' => '../foto.png', 'storage_key' => '../arquivo.php',
            'mime_type' => 'image/svg+xml', 'size_bytes' => 0, 'width' => 513, 'height' => -1,
        ]);
        $this->assertFalse($photo->isValid());
        foreach (['user_id', 'original_name', 'storage_key', 'mime_type', 'size_bytes', 'width', 'height'] as $field) {
            $this->assertNotNull($photo->errors($field), $field);
        }
        $this->assertSame([], ProfilePhoto::all());
    }

    public function test_initials_use_name_and_fall_back_to_email(): void
    {
        $this->assertSame('MV', (new User(['name' => 'Matheus Venger']))->profileInitials());
        $this->assertSame('ÁS', (new User(['name' => 'Álvaro Silva']))->profileInitials());
        $this->assertSame('M', (new User(['email' => 'matheus@example.com']))->profileInitials());
    }

    public function test_additive_migration_is_repeatable_and_preserves_existing_data(): void
    {
        $owner = Fixtures::user();
        $photo = ProfileFixtures::photo($owner, true);
        $sql = file_get_contents(__DIR__ . '/../../../database/migrations/20261108_profile_photos.sql');
        $this->assertIsString($sql);
        Database::exec($sql);
        Database::exec($sql);
        $this->assertSame($owner->email, User::findById($owner->id)?->email);
        $this->assertSame($photo->id, $owner->currentProfilePhoto()?->id);
        $this->assertFileExists((new ProfileAvatar())->filePath($photo));
    }

    public function test_file_is_restored_when_database_rejects_deletion(): void
    {
        $owner = Fixtures::user();
        $photo = ProfileFixtures::photo($owner, true);
        $service = new ProfileAvatar();
        $path = $service->filePath($photo);
        $before = hash_file('sha256', $path);
        Database::exec(<<<'SQL'
            CREATE FUNCTION reject_photo_delete() RETURNS trigger LANGUAGE plpgsql AS $$
            BEGIN RAISE EXCEPTION 'Exclusão bloqueada pelo teste'; END $$;
            CREATE TRIGGER reject_photo_delete BEFORE DELETE ON profile_photos
                FOR EACH ROW EXECUTE FUNCTION reject_photo_delete();
            SQL);
        try {
            $service->remove($owner, $photo);
            $this->fail('A exclusão deveria falhar.');
        } catch (PDOException $exception) {
            $this->assertSame('P0001', $exception->getCode());
        }
        $this->assertNotNull(ProfilePhoto::findById($photo->id));
        $this->assertFileExists($path);
        $this->assertSame($before, hash_file('sha256', $path));
    }
}
