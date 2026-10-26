<?php

namespace Tests\Unit;

use App\Support\OpeningHours;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class OpeningHoursTest extends TestCase
{
    private function hours(): OpeningHours
    {
        $weekday = ['09:00', '21:00'];

        return new OpeningHours(
            [1 => $weekday, 2 => $weekday, 3 => $weekday, 4 => $weekday, 5 => $weekday, 6 => ['09:30', '14:00'], 7 => null],
            'Europe/Madrid',
        );
    }

    /**
     * A moment in Madrid. 26 October 2026 is a Monday.
     */
    private function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse($time, 'Europe/Madrid');
    }

    public function test_open_during_opening_hours(): void
    {
        $this->assertSame(
            ['open' => true, 'closes' => '21:00', 'opens' => null],
            $this->hours()->status($this->at('2026-10-26 10:00')),
        );
    }

    public function test_before_opening_it_opens_today(): void
    {
        $this->assertSame(
            ['open' => false, 'closes' => null, 'opens' => 'today at 9:00'],
            $this->hours()->status($this->at('2026-10-26 08:59')),
        );
    }

    public function test_closed_from_closing_time(): void
    {
        $this->assertSame(
            ['open' => false, 'closes' => null, 'opens' => 'tomorrow at 9:00'],
            $this->hours()->status($this->at('2026-10-26 21:00')),
        );
    }

    public function test_after_saturday_closing_it_opens_on_monday(): void
    {
        $this->assertSame(
            ['open' => false, 'closes' => null, 'opens' => 'Monday at 9:00'],
            $this->hours()->status($this->at('2026-10-31 14:30')),
        );
    }

    public function test_open_and_closed_are_judged_in_the_pharmacy_timezone(): void
    {
        // Madrid is UTC+1 in winter: 19:30 UTC is 20:30 there (open), 20:30 UTC is 21:30 (closed)
        $this->assertTrue($this->hours()->status(CarbonImmutable::parse('2026-10-26 19:30', 'UTC'))['open']);
        $this->assertFalse($this->hours()->status(CarbonImmutable::parse('2026-10-26 20:30', 'UTC'))['open']);
    }

    public function test_never_open_has_no_next_opening(): void
    {
        $closed = new OpeningHours(array_fill(1, 7, null), 'Europe/Madrid');

        $this->assertSame(['open' => false, 'closes' => null, 'opens' => null], $closed->status($this->at('2026-10-26 10:00')));
    }

    public function test_summary_folds_days_with_the_same_hours(): void
    {
        $this->assertSame([
            ['Monday to Friday', '9:00–21:00'],
            ['Saturday', '9:30–14:00'],
            ['Sunday', 'Closed'],
        ], $this->hours()->summary());
    }
}
