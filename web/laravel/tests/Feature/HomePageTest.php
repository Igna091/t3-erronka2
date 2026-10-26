<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    public function test_landing_page_shows_every_section(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Pick it up on your way home.')
            ->assertSee('How a refill works')
            ->assertSee('What’s inside')
            ->assertSee('A week of tablets, sorted for you')
            ->assertSee('Visit us')
            ->assertSee('Monday to Friday');
    }

    public function test_opening_status_follows_the_clock(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-26 10:00', 'Europe/Madrid'));
        $this->get('/')->assertSee('Open until 21:00')->assertSee('Open now');

        $this->travelTo(CarbonImmutable::parse('2026-10-26 22:00', 'Europe/Madrid'));
        $this->get('/')->assertSee('Opens tomorrow at 9:00')->assertSee('Closed now');
    }

    public function test_page_uses_nothing_the_content_security_policy_blocks(): void
    {
        $this->assertCspSafe($this->get('/')->getContent());
    }

    public function test_header_links_to_the_shop(): void
    {
        $this->get('/')->assertSee('href="'.route('shop.index').'"', false);
    }
}
