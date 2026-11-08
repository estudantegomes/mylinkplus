<?php

namespace Tests\Unit\Models;

use App\Models\Folder;
use Tests\Support\Fixtures;
use Tests\TestCase;

class FolderTest extends TestCase
{
    public function test_root_has_null_parent_and_subfolder_can_use_a_parent_of_the_same_user(): void
    {
        $owner = Fixtures::user();
        $root = new Folder(['user_id' => $owner->id, 'name' => 'Raiz', 'parent_id' => null]);

        $this->assertTrue($root->save());
        $savedRoot = Folder::findById($root->id);
        $this->assertNotNull($savedRoot);
        $this->assertNull($savedRoot->parent_id);

        $child = new Folder([
            'user_id' => $owner->id,
            'name' => 'Subpasta',
            'parent_id' => (string) $root->id,
        ]);

        $this->assertTrue($child->save());
        $savedChild = Folder::findById($child->id);
        $this->assertNotNull($savedChild);
        $this->assertSame($owner->id, $savedChild->user_id);
        $this->assertSame($root->id, $savedChild->parent_id);
    }

    public function test_subfolder_rejects_nonexistent_parent(): void
    {
        $owner = Fixtures::user();
        $child = new Folder(['user_id' => $owner->id, 'name' => 'Subpasta', 'parent_id' => 999999]);

        $this->assertFalse($child->save());
        $this->assertNotNull($child->errors('parent_id'));
        $this->assertSame([], Folder::where(['user_id' => $owner->id]));
    }

    public function test_subfolder_rejects_parent_owned_by_another_user(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outra-conta@example.com');
        $foreignParent = new Folder(['user_id' => $other->id, 'name' => 'Pasta privada']);

        $this->assertTrue($foreignParent->save());

        $child = new Folder(['user_id' => $owner->id, 'name' => 'Subpasta', 'parent_id' => $foreignParent->id]);

        $this->assertFalse($child->save());
        $this->assertNotNull($child->errors('parent_id'));
        $this->assertSame([], Folder::where(['user_id' => $owner->id]));
    }

    public function test_subfolder_rejects_invalid_parent_ids_without_saving_as_root(): void
    {
        $owner = Fixtures::user();

        foreach (['abc', '', 0, -1, '2147483648', '1.5', ['1'], true, 1.0] as $parentId) {
            $child = new Folder(['user_id' => $owner->id, 'name' => 'Subpasta', 'parent_id' => $parentId]);

            $this->assertFalse($child->save(), 'O identificador informado não deveria ser aceito.');
            $this->assertNotNull($child->errors('parent_id'));
        }

        $this->assertSame([], Folder::where(['user_id' => $owner->id]));
    }
}
