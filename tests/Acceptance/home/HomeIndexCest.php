<?php

namespace Tests\Acceptance\home;

use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;

class HomeIndexCest extends BaseAcceptanceCest
{
    public function seeHomePage(AcceptanceTester $page): void
    {
        $page->amOnPage('/');

        $page->see('Bem-vindo de volta!', '#login-title');
        $page->seeElement('#mylink-login-form');
    }
}
