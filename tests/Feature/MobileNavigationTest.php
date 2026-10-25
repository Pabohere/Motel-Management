<?php

namespace Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_gets_bottom_navigation_and_current_monthly_page_in_the_more_menu(): void
    {
        $manager = User::factory()->create(['role' => 'manager']);
        $response = $this->actingAs($manager)->get(route('reports.monthly'))->assertOk();
        $xpath = $this->navigation($response->getContent());
        $this->assertSame(4, $xpath->query('//nav[@class="mobile-bottom-nav"]/a')->length);
        $this->assertSame(1, $xpath->query('//nav[@class="mobile-bottom-nav"]/button[contains(@class,"active")][@aria-controls="mobile-menu"]')->length);
        $this->assertSame(route('reports.monthly'), $xpath->query('//dialog[@id="mobile-menu"]//a[@aria-current="page"]')->item(0)->getAttribute('href'));
        foreach (['reports.daily', 'users.index', 'audit.index', 'password.edit'] as $route) {
            $this->assertSame(1, $xpath->query('//dialog[@id="mobile-menu"]//a[@href="'.route($route).'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//dialog[@id="mobile-menu"]//form[@action="'.route('logout').'"]//input[@name="_token"]')->length);
    }

    public function test_staff_navigation_preserves_role_restrictions_and_active_primary_tab(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $response = $this->actingAs($staff)->get(route('stays.index'))->assertOk();
        $xpath = $this->navigation($response->getContent());
        $this->assertSame(route('stays.index'), $xpath->query('//nav[@class="mobile-bottom-nav"]/a[@aria-current="page"]')->item(0)->getAttribute('href'));
        foreach (['reports.monthly', 'users.index', 'audit.index'] as $route) {
            $this->assertSame(0, $xpath->query('//dialog[@id="mobile-menu"]//a[@href="'.route($route).'"]')->length);
        }
        $this->assertSame(1, $xpath->query('//dialog[@id="mobile-menu"]//a[@href="'.route('reports.daily').'"]')->length);
        $this->assertSame(0, $xpath->query('//nav[@class="mobile-bottom-nav"]/button[contains(@class,"active")]')->length);
    }

    private function navigation(string $html): DOMXPath
    {
        $document = new DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new DOMXPath($document);
    }
}
