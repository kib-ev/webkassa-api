<?php

declare(strict_types=1);

namespace WebKassa\Support;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class PeriodResolver
{
    public function __construct(
        private readonly string $timezone = 'Europe/Minsk',
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    public function resolve(array $options): array
    {
        $tz = new DateTimeZone($this->timezone);

        if (isset($options['month'])) {
            if (! preg_match('/^\d{4}-\d{2}$/', (string) $options['month'])) {
                throw new InvalidArgumentException('Invalid --month format. Expected YYYY-MM.');
            }

            $from = new DateTimeImmutable($options['month'] . '-01 00:00:00', $tz);

            return [$from, $from->modify('last day of this month')];
        }

        if (! isset($options['from'], $options['to'])) {
            throw new InvalidArgumentException('Specify --month=YYYY-MM or both --from and --to.');
        }

        $from = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $options['from'] . ' 00:00:00', $tz);
        $to = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $options['to'] . ' 00:00:00', $tz);

        if ($from === false || $to === false) {
            throw new InvalidArgumentException('Invalid date format. Expected YYYY-MM-DD.');
        }

        if ($from > $to) {
            throw new InvalidArgumentException('--from cannot be later than --to.');
        }

        return [$from, $to];
    }
}
