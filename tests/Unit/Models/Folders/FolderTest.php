<?php

namespace Tests\Unit\Models\Folders;

use App\Models\Folder;
use App\Models\User;
use Tests\TestCase;

class FolderTest extends TestCase
{
    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = new User([
            'name' => 'User Folder Test',
            'email' => 'folder_test@example.com',
            'password' => '123456',
            'password_confirmation' => '123456'
        ]);
        $this->user->save();
    }

    public function test_create_folder_valid(): void
    {
        $folder = new Folder([
            'user_id' => $this->user->id,
            'name' => 'Projetos',
            'color' => '#507cf3'
        ]);

        $this->assertTrue($folder->save());
        $this->assertGreaterThan(0, $folder->id);
    }

    public function test_create_subfolder(): void
    {
        $parent = new Folder([
            'user_id' => $this->user->id,
            'name' => 'Trabalho',
            'color' => '#123456'
        ]);
        $parent->save();

        $child = new Folder([
            'user_id' => $this->user->id,
            'parent_id' => $parent->id,
            'name' => 'Relatórios',
            'color' => '#654321'
        ]);
        $child->save();

        $this->assertEquals($parent->id, $child->parent_id);
    }

    public function test_validation_empty_name(): void
    {
        $folder = new Folder([
            'user_id' => $this->user->id,
            'name' => '',
        ]);

        $this->assertFalse($folder->save());
    }

    public function test_validation_invalid_color(): void
    {
        $folder = new Folder([
            'user_id' => $this->user->id,
            'name' => 'Teste Cor',
            'color' => 'invalid-hex'
        ]);

        $this->assertFalse($folder->save());
    }
}
