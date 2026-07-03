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
use WebKassa\Support\EposPaymentNormalizer;

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
        [$fromMs, $toMs] = $this->periodToMilliseconds($from, $to);

        return $this->client()->getEposPayReport($fromMs, $toMs);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eposPayReportBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        return $this->client()->getEposPayReport(
            $this->toMilliseconds($from),
            $this->toMilliseconds($to),
        );
    }

    /**
     * @param array<string, mixed>|object|null $filters
     * @return array<int, array<string, mixed>>
     */
    public function eposInvoices(array|object|null $filters = null): array
    {
        return $this->client()->getEposInvoices($filters);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eposPayments(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $payments = $this->eposPayReport($from, $to);

        if ($payments === []) {
            return [];
        }

        $normalizer = new EposPaymentNormalizer($this->config);
        $invoiceMap = $normalizer->buildInvoiceMap($this->eposInvoices());

        return $normalizer->normalizePayments($payments, $invoiceMap);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function eposPaymentsBetween(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $payments = $this->eposPayReportBetween($from, $to);

        if ($payments === []) {
            return [];
        }

        $normalizer = new EposPaymentNormalizer($this->config);
        $invoiceMap = $normalizer->buildInvoiceMap($this->eposInvoices());

        return $normalizer->normalizePayments($payments, $invoiceMap);
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
    private function periodToMilliseconds(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $tz = new DateTimeZone($this->config->timezone);

        $fromLocal = new DateTimeImmutable($from->format('Y-m-d') . ' 00:00:00', $tz);
        $toLocal = new DateTimeImmutable($to->format('Y-m-d') . ' 23:59:59', $tz);

        return [
            $fromLocal->getTimestamp() * 1000,
            $toLocal->getTimestamp() * 1000 + 999,
        ];
    }

    private function toMilliseconds(DateTimeInterface $moment): int
    {
        return $moment->getTimestamp() * 1000;
    }
}
