<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_authenticated_pages_offer_the_same_installable_app(): void
    {
        foreach ([$this->get(route('login')), $this->actingAs(User::factory()->create())->get(route('rooms.index'))] as $response) {
            $response->assertOk()->assertSee('manifest.webmanifest')->assertSee('apple-touch-icon')->assertSee('pwa.js')->assertSee('pwa-install-dialog')->assertSee('data-pwa-install', false);
        }
    }

    public function test_manifest_icons_and_offline_page_are_public_and_self_contained(): void
    {
        $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/', $manifest['id']);
        $this->assertSame('/', $manifest['scope']);
        $this->assertSame('/', $manifest['start_url']);
        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $image = getimagesize(public_path(ltrim($icon['src'], '/')));
            $this->assertSame($icon['sizes'], $image[0].'x'.$image[1]);
            $this->assertSame('image/png', $image['mime']);
        }
        $appleIcon = getimagesize(public_path('icons/motel-180.png'));
        $this->assertSame([180, 180], [$appleIcon[0], $appleIcon[1]]);
        $offline = file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString('Try again', $offline);
        $this->assertStringNotContainsString('<script', $offline);
        $this->assertStringNotContainsString('<form', $offline);
    }
}
