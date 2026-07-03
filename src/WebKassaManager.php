<?php

declare(strict_types=1);

namespace WebKassa;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use WebKassa\Client\WebKassaClient;
use WebKassa\Config\WebKassaConfig;
use WebKassa\Contracts\WebKassaClientInterface;
use WebKassa\Export\EposReportExporter;

final class WebKassaManager
{
    private ?WebKassaClientInterface $client = null;

    public function __construct(
        private readonly WebKassaConfig $config,
    ) {
    }

    public function config(): WebKassaConfig
    {
        return $this->config;
    }

    public function client(): WebKassaClientInterface
    {
        return $this->client ??= new WebKassaClient($this->config);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eposPayReport(DateTimeInterface $from, DateTimeInterface $to): array
    {
        [$fromMs, $toMs] = $this->periodToUtcMilliseconds($from, $to);

        return $this->client()->getEposPayReport($fromMs, $toMs);
    }

    /**
     * @param array<string, mixed>|object|null $filters
     * @return array<int, array<string, mixed>>
     */
    public function eposInvoices(array|object|null $filters = null): array
    {
        return $this->client()->getEposInvoices($filters);
    }

    public function exportEposReport(DateTimeInterface $from, DateTimeInterface $to, string $outputPath): int
    {
        return $this->eposReportExporter()->exportPeriod($from, $to, $outputPath);
    }

    public function eposReportExporter(): EposReportExporter
    {
        return new EposReportExporter($this->client(), $this->config);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function periodToUtcMilliseconds(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $utc = new DateTimeZone('UTC');

        $fromUtc = new DateTimeImmutable($from->format('Y-m-d') . ' 00:00:00', $utc);
        $toUtc = new DateTimeImmutable($to->format('Y-m-d') . ' 23:59:59', $utc);

        return [
            $fromUtc->getTimestamp() * 1000,
            $toUtc->getTimestamp() * 1000 + 999,
        ];
    }
}
