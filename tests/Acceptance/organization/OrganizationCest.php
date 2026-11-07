<?php

namespace Tests\Acceptance\organization;

use App\Models\Folder;
use App\Models\Tag;
use PHPUnit\Framework\Assert;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;
use Tests\Support\Fixtures;

class OrganizationCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        $page->amOnPage('/login');
        $page->resetCookie('PHPSESSID');
        Fixtures::user();
        $page->login('teste@example.com', 'Senha@12345');
        $page->waitForElement('#folders-title');
    }

    public function completeCrudAndPagination(AcceptanceTester $page): void
    {
        $owner = \App\Models\User::findByEmail('teste@example.com');
        Assert::assertNotNull($owner);

        foreach (['folders' => Folder::class, 'tags' => Tag::class] as $resource => $class) {
            $page->amOnPage('/' . $resource . '/new');
            $page->click('#organization-form button[type=submit]');
            $page->see('Informe um nome', '[data-error=name]');

            $page->fillField('name', 'Inicial');
            $page->selectOption('input[name="color"]', '#507CF3');
            $page->click('#organization-form button[type=submit]');
            $page->waitForElement('#item-title');
            $page->see('Inicial', '#item-title');

            $page->click('Editar');
            $page->fillField('name', '');
            $page->click('#organization-form button[type=submit]');
            $page->see('Informe um nome', '[data-error=name]');
            $page->fillField('name', 'Atualizado');
            $page->click('#organization-form button[type=submit]');
            $page->waitForElement('#item-title');
            $page->see('Atualizado', '#item-title');

            $page->amOnPage('/' . $resource);
            $page->see('Atualizado', '#organization-table');
            $page->click('Detalhes');
            $page->click('Excluir');
            $page->acceptPopup();
            $page->waitForElement('#organization-table');
            $page->dontSee('Atualizado', '#organization-table');

            for ($number = 1; $number <= 11; $number++) {
                $item = new $class(['user_id' => $owner->id, 'name' => sprintf('Item %02d', $number)]);
                Assert::assertTrue($item->save());
            }
            $page->amOnPage('/' . $resource);
            $page->see('Item 01', '#organization-table');
            $page->dontSee('Item 11', '#organization-table');
            $page->click('Próxima');
            $page->see('Item 11', '#organization-table');
            $page->dontSee('Item 01', '#organization-table');
            $page->click('Anterior');
            $page->see('Item 01', '#organization-table');
        }
    }

    public function subfoldersCannotCreateCyclesOrBeDeletedByCascade(AcceptanceTester $page): void
    {
        $owner = \App\Models\User::findByEmail('teste@example.com');
        Assert::assertNotNull($owner);
        $parent = new Folder(['user_id' => $owner->id, 'name' => 'Estudos']);
        Assert::assertTrue($parent->save());

        $page->amOnPage('/folders/new?parent_id=' . $parent->id);
        $page->fillField('name', 'Programação');
        $page->click('#organization-form button[type=submit]');
        $page->amOnPage('/folders?parent_id=' . $parent->id);
        $page->see('Programação', '#organization-table');

        $child = Folder::findBy(['user_id' => $owner->id, 'name' => 'Programação']);
        Assert::assertNotNull($child);
        $page->amOnPage('/folders/' . $parent->id . '/edit');
        $page->selectOption('parent_id', (string) $child->id);
        $page->click('#organization-form button[type=submit]');
        $page->see('ciclo', '[data-error=parent_id]');

        $page->amOnPage('/folders/' . $parent->id);
        $page->click('Excluir');
        $page->acceptPopup();
        $page->waitForElement('#item-title');
        $page->see('Exclua ou mova as subpastas');
    }
}
