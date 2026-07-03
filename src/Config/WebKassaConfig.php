<?php

declare(strict_types=1);

namespace WebKassa\Config;

final class WebKassaConfig
{
    public function __construct(
        public readonly string $token,
        public readonly string $baseUrl = 'https://cabinet.webkassa.by/api',
        public readonly int $timeout = 120,
        public readonly string $timezone = 'Europe/Minsk',
        public readonly string $merchantName = '',
        public readonly string $merchantUnp = '',
        public readonly string $defaultEposService = 'Аренда',
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $merchant = is_array($config['merchant'] ?? null) ? $config['merchant'] : [];
        $epos = is_array($config['epos'] ?? null) ? $config['epos'] : [];

        return new self(
            token: (string) ($config['token'] ?? ''),
            baseUrl: rtrim((string) ($config['base_url'] ?? 'https://cabinet.webkassa.by/api'), '/'),
            timeout: (int) ($config['timeout'] ?? 120),
            timezone: (string) ($config['timezone'] ?? 'Europe/Minsk'),
            merchantName: (string) ($merchant['name'] ?? ''),
            merchantUnp: (string) ($merchant['unp'] ?? ''),
            defaultEposService: (string) ($epos['default_service'] ?? 'Аренда'),
        );
    }
}
