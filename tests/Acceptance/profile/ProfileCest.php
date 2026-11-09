<?php

namespace Tests\Acceptance\profile;

use App\Models\ProfilePhoto;
use App\Services\ProfileAvatar;
use PHPUnit\Framework\Assert;
use Tests\Acceptance\BaseAcceptanceCest;
use Tests\Support\AcceptanceTester;
use Tests\Support\Fixtures;
use Tests\Support\ProfileFixtures;

class ProfileCest extends BaseAcceptanceCest
{
    public function _before(AcceptanceTester $page): void
    {
        parent::_before($page);
        ProfileFixtures::clearFiles();
        Fixtures::user();
        $page->amOnPage('/login');
        $page->resetCookie('PHPSESSID');
        $page->login('teste@example.com', 'Senha@12345');
        $page->waitForElement('#profile-menu');
        $page->click('#profile-menu');
        $page->waitForElement('#profile-upload-form');
    }

    public function _after(AcceptanceTester $page): void
    {
        ProfileFixtures::clearFiles();
        parent::_after($page);
    }

    public function uploadMultiplePhotosAndChooseCurrent(AcceptanceTester $page): void
    {
        foreach (['avatar.png', 'avatar.jpg', 'avatar.webp'] as $name) {
            $this->upload($page, $name);
            $page->see($name, '#profile-gallery');
        }
        $page->seeNumberOfElements('.profile-photo-card', 3);
        $photos = ProfilePhoto::all();
        Assert::assertCount(3, $photos);
        $service = new ProfileAvatar();
        foreach ($photos as $photo) {
            Assert::assertFileExists($service->filePath($photo));
        }
        $selector = '[data-photo-id="' . $photos[0]->id . '"] .profile-select-button';
        $page->scrollTo($selector, 0, -100);
        $page->click($selector);
        $page->waitForElement('[data-photo-id="' . $photos[0]->id . '"][data-current="true"]');
    }

    public function viewImageAndAvatarOnHome(AcceptanceTester $page): void
    {
        $this->upload($page, 'avatar.png');
        $page->waitForJS(
            'return [...document.querySelectorAll("#profile-gallery img")].every(i => i.complete && i.naturalWidth > 0)'
        );
        $photo = ProfilePhoto::all()[0];
        $page->amOnPage('/home');
        $page->seeElement('#profile-menu img[src="/profile/photos/' . $photo->id . '/image"]');
        $page->amOnPage('/profile/photos/' . $photo->id . '/image');
        $page->waitForJS(
            'return document.images.length > 0 && document.images[0].complete && document.images[0].naturalWidth > 0'
        );
    }

    public function removePhotoAlsoRemovesFile(AcceptanceTester $page): void
    {
        $this->upload($page, 'avatar.png');
        $photo = ProfilePhoto::all()[0];
        $path = (new ProfileAvatar())->filePath($photo);
        Assert::assertFileExists($path);
        $page->scrollTo('.profile-delete-button', 0, -100);
        $page->click('.profile-delete-button');
        $page->waitForElement('#profile-empty');
        Assert::assertNull(ProfilePhoto::findById($photo->id));
        Assert::assertFileDoesNotExist($path);
        $page->amOnPage('/home');
        $page->see('UT', '#profile-menu');
    }

    public function rejectSvgWithValidationMessage(AcceptanceTester $page): void
    {
        $page->attachFile('photo', 'blocked.svg');
        $page->click('#profile-upload-form button[type=submit]');
        $page->waitForElement('#photo-error');
        $page->see('Formato inválido', '#photo-error');
        $page->seeElement('#profile-empty');
        Assert::assertSame([], ProfilePhoto::all());
    }

    private function upload(AcceptanceTester $page, string $name): void
    {
        $page->attachFile('photo', $name);
        $page->scrollTo('#profile-upload-form button[type=submit]', 0, -100);
        $page->click('#profile-upload-form button[type=submit]');
        $page->waitForElement('.profile-photo-card[data-current="true"]');
    }
}
