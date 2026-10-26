<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * The pharmacy's weekly opening hours (config/pharmacy.php): whether it is open at a given
 * moment, when it opens next, and the rows listed under "Visit us".
 */
class OpeningHours
{
    private const DAYS = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /**
     * @param  array<int, array{0: string, 1: string}|null>  $week  ISO weekday (1 = Monday) => [opens, closes] as "HH:MM", or null when closed
     */
    public function __construct(
        private readonly array $week,
        private readonly string $timezone,
    ) {}

    public static function fromConfig(): self
    {
        return new self(config('pharmacy.hours'), config('pharmacy.timezone'));
    }

    /**
     * Whether the pharmacy is open at $at (default: now), with its closing time if it is, or when
     * it opens next ("today at 9:00", "tomorrow at 9:00", "Monday at 9:30") if it is not.
     *
     * @return array{open: bool, closes: ?string, opens: ?string}
     */
    public function status(?CarbonImmutable $at = null): array
    {
        $now = ($at ?? now()->toImmutable())->setTimezone($this->timezone);
        $time = $now->format('H:i');
        $today = $this->week[$now->dayOfWeekIso] ?? null;

        if ($today !== null && $time >= $today[0] && $time < $today[1]) {
            return ['open' => true, 'closes' => self::clock($today[1]), 'opens' => null];
        }

        for ($ahead = 0; $ahead <= 7; $ahead++) {
            $day = $now->addDays($ahead);
            $hours = $this->week[$day->dayOfWeekIso] ?? null;

            if ($hours !== null && ($ahead > 0 || $time < $hours[0])) {
                $when = match ($ahead) {
                    0 => 'today',
                    1 => 'tomorrow',
                    default => self::DAYS[$day->dayOfWeekIso],
                };

                return ['open' => false, 'closes' => null, 'opens' => $when.' at '.self::clock($hours[0])];
            }
        }

        return ['open' => false, 'closes' => null, 'opens' => null];
    }

    /**
     * Consecutive days with the same hours folded into one row, e.g. ["Monday to Friday", "9:00–21:00"].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function summary(): array
    {
        $rows = [];
        $from = 1;

        foreach (self::DAYS as $day => $name) {
            $hours = $this->week[$day] ?? null;

            if ($day < 7 && ($this->week[$day + 1] ?? null) === $hours) {
                continue;
            }

            $rows[] = [
                $from === $day ? $name : self::DAYS[$from].' to '.$name,
                $hours === null ? 'Closed' : self::clock($hours[0]).'–'.self::clock($hours[1]),
            ];
            $from = $day + 1;
        }

        return $rows;
    }

    /**
     * "09:30" the way the design writes it: "9:30".
     */
    private static function clock(string $time): string
    {
        return ltrim($time[0], '0').substr($time, 1);
    }
}
