<?php

namespace Tests\Acceptance\users;

use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class RegistrationCest extends BaseAcceptanceCest
{
    public function invalidAndSuccessfulRegistrationThenLogin(AcceptanceTester $page): void
    {
        $page->amOnPage('/login');
        $page->resetCookie('PHPSESSID');
        $page->amOnPage('/users/new');

        $page->click('#registration-form button[type=submit]');
        $page->see('não pode ser vazio', '[data-error=name]');

        $page->fillField('name', 'Matheus');
        $page->fillField('email', 'matheus@example.com');
        $page->fillField('password', 'Senha@12345');
        $page->fillField('password_confirmation', 'OutraSenha');
        $page->click('#registration-form button[type=submit]');
        $page->see('as senhas devem ser idênticas', '[data-error=password]');

        $page->fillField('password', 'Senha@12345');
        $page->fillField('password_confirmation', 'Senha@12345');
        $page->click('#registration-form button[type=submit]');
        $page->waitForElement('#mylink-login-form');
        $page->seeCurrentUrlEquals('/login');

        $page->login('matheus@example.com', 'Senha@12345');
        $page->waitForElement('#folders-title');
        $page->seeCurrentUrlEquals('/home');
    }
}
