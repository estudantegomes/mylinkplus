<?php

namespace Tests\Unit\Models;

use App\Models\Folder;
use App\Models\Tag;
use Lib\Paginator;
use Tests\Support\Fixtures;
use Tests\TestCase;

class OrganizationTest extends TestCase
{
    public function test_crud_validation_and_pagination_are_scoped_by_owner(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outro@example.com');

        foreach ([Folder::class, Tag::class] as $class) {
            $first = new $class(['user_id' => $owner->id, 'name' => 'Inicial']);
            $second = new $class(['user_id' => $owner->id, 'name' => 'Segundo']);
            $foreign = new $class(['user_id' => $other->id, 'name' => 'Privado']);

            $this->assertTrue($first->save());
            $this->assertTrue($second->save());
            $this->assertTrue($foreign->save());

            $first->name = 'Renomeado';

            $this->assertTrue($first->save());
            $this->assertSame('Renomeado', $class::findById($first->id)?->name);

            $first->name = ' ';

            $this->assertFalse($first->save());
            $this->assertSame('Renomeado', $class::findById($first->id)?->name);

            $first->name = 'Renomeado';

            $conditions = ['user_id' => $owner->id];

            if ($class === Folder::class) {
                $conditions['parent_id'] = null;

                $child = new Folder(['user_id' => $owner->id, 'parent_id' => $first->id, 'name' => 'Filha']);

                $this->assertTrue($child->save());
                $this->assertFalse($first->destroy());
                $this->assertTrue($child->destroy());
            }

            $paginator = new Paginator($class, 2, 1, $class::table(), $class::columns(), $conditions);

            $this->assertSame(2, $paginator->totalOfRegisters());
            $this->assertCount(1, $paginator->registers());
            $this->assertSame($second->id, $paginator->registers()[0]->id);
            $this->assertTrue($paginator->hasPreviousPage());
            $this->assertFalse($paginator->hasNextPage());

            $this->assertTrue($first->destroy());
            $this->assertNull($class::findById($first->id));
        }
    }

    public function test_folder_rejects_foreign_parent_self_and_descendant(): void
    {
        $owner = Fixtures::user();
        $other = Fixtures::user('outro@example.com');

        $root = new Folder(['user_id' => $owner->id, 'name' => 'Raiz']);
        $this->assertTrue($root->save());

        $child = new Folder(['user_id' => $owner->id, 'parent_id' => $root->id, 'name' => 'Filha']);
        $this->assertTrue($child->save());

        $root->parent_id = $child->id;
        $this->assertFalse($root->save());

        $root->parent_id = $root->id;
        $this->assertFalse($root->save());

        $foreign = new Folder(['user_id' => $other->id, 'parent_id' => $root->id, 'name' => 'Inválida']);

        $this->assertFalse($foreign->save());
        $this->assertNotNull($foreign->errors('parent_id'));
    }

    public function test_tag_name_is_unique_per_owner_and_color_is_validated(): void
    {
        $owner = Fixtures::user();

        $tag = new Tag(['user_id' => $owner->id, 'name' => 'Estudos']);
        $this->assertTrue($tag->save());

        $duplicate = new Tag(['user_id' => $owner->id, 'name' => 'Estudos']);
        $this->assertFalse($duplicate->save());

        $tag->color = 'azul';
        $this->assertFalse($tag->save());

        $tag->color = '#507CF3';
        $this->assertTrue($tag->save());

        $tag->name = str_repeat('á', 101);
        $this->assertFalse($tag->save());
    }
}
