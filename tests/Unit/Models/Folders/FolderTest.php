<?php

namespace Tests\Unit\Models\Folders;

use App\Models\Folder;
use App\Models\User;
use Tests\TestCase;

class FolderTest extends TestCase
{
    private User $user;
    private Folder $folder;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'name' => 'User Test',
            'email' => 'test@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();

        $this->folder = new Folder([
            'user_id' => $this->user->id,
            'name' => 'Estudos',
            'color' => '#507cf3'
        ]);
        $this->folder->save();
    }

    public function test_should_create_new_folder(): void
    {
        $this->assertCount(1, Folder::all());
        $this->assertEquals('Estudos', Folder::findById($this->folder->id)->name);
    }

    public function test_should_validate_required_fields(): void
    {
        $folder = new Folder();

        $this->assertFalse($folder->isValid());
        $this->assertEquals('não pode ser vazio!', $folder->errors('name'));
        $this->assertEquals('não pode ser vazio!', $folder->errors('user_id'));
    }

    public function test_belongs_to_user(): void
    {
        $user = $this->folder->user()->get();
        $this->assertEquals($this->user->id, $user->id);
    }

    public function test_has_many_folders_on_user(): void
    {
        $folders = $this->user->folders()->get();

        $this->assertCount(1, $folders);
        $this->assertEquals('Estudos', $folders[0]->name);
    }

    public function test_destroy_should_remove_folder(): void
    {
        $this->folder->destroy();
        $this->assertCount(0, Folder::all());
    }
}
