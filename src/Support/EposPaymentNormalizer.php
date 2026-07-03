<?php

declare(strict_types=1);

namespace WebKassa\Support;

use DateTimeImmutable;
use DateTimeZone;
use WebKassa\Config\WebKassaConfig;

final class EposPaymentNormalizer
{
    private readonly DateTimeZone $timezone;

    public function __construct(
        private readonly WebKassaConfig $config,
    ) {
        $this->timezone = new DateTimeZone($this->config->timezone);
    }

    /**
     * @param array<int, array<string, mixed>> $invoices
     * @return array<int, array<string, mixed>>
     */
    public function buildInvoiceMap(array $invoices): array
    {
        $map = [];

        foreach ($invoices as $invoice) {
            $invoiceId = (int) ($invoice['invoiceId'] ?? 0);
            if ($invoiceId > 0) {
                $map[$invoiceId] = $invoice;
            }
        }

        return $map;
    }

    /**
     * @param array<int, array<string, mixed>> $payments
     * @param array<int, array<string, mixed>> $invoiceMap
     * @return array<int, array<string, mixed>>
     */
    public function normalizePayments(array $payments, array $invoiceMap): array
    {
        $rows = [];

        foreach ($payments as $index => $payment) {
            $rows[] = $this->normalizePayment($payment, $invoiceMap, $index + 1);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $payment
     * @param array<int, array<string, mixed>> $invoiceMap
     * @return array<string, mixed>
     */
    public function normalizePayment(array $payment, array $invoiceMap, int $rowNumber): array
    {
        $invoiceId = (int) ($payment['invoiceId'] ?? 0);
        $invoice = $invoiceMap[$invoiceId] ?? null;
        $payerRaw = trim((string) ($payment['payerName'] ?? ''));
        [$payerPhone, $payerName] = $this->splitPayer($payerRaw);

        $accountNumber = $invoice['invoiceNumber'] ?? $this->extractAccountNumber((string) ($payment['fullEposNumber'] ?? ''));

        return [
            'row_number' => $rowNumber,
            'status' => 'Оплачен',
            'amount' => round((float) ($payment['totalSum'] ?? 0), 2),
            'net_amount' => isset($payment['transferAmount'])
                ? round((float) $payment['transferAmount'], 2)
                : null,
            'paid_at' => $this->formatTimestamp((int) ($payment['paymentDate'] ?? 0)),
            'operation_number' => $this->normalizeOperationNumber($payment['transactionId'] ?? null),
            'payment_method' => $this->formatPaymentType((string) ($payment['preDocTypeString'] ?? '')),
            'epos_service' => (string) ($invoice['eposService'] ?? $this->config->defaultEposService),
            'payer_raw' => $payerRaw !== '' ? $payerRaw : null,
            'payer_phone' => $payerPhone,
            'payer_name' => $payerName,
            'invoice_created_at' => isset($invoice['invoiceDate'])
                ? $this->formatTimestamp((int) $invoice['invoiceDate'])
                : null,
            'account_number' => $accountNumber !== '' ? (string) $accountNumber : null,
            'terminal_sn' => null,
            'merchant_code' => null,
            'webkassa_invoice_id' => $invoiceId > 0 ? $invoiceId : null,
            'raw_row' => [
                'payment' => $payment,
                'invoice' => $invoice,
            ],
        ];
    }

    private function formatPaymentType(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return 'Оплата счета';
        }

        return str_ends_with($value, ' e-pos')
            ? substr($value, 0, -strlen(' e-pos'))
            : $value;
    }

    private function formatTimestamp(int $timestampMs): ?string
    {
        if ($timestampMs <= 0) {
            return null;
        }

        return (new DateTimeImmutable('@' . intdiv($timestampMs, 1000)))
            ->setTimezone($this->timezone)
            ->format('Y-m-d H:i:s');
    }

    private function extractAccountNumber(string $fullEposNumber): string|int
    {
        if (preg_match('/-i(\d+)$/', $fullEposNumber, $matches) === 1) {
            return (int) $matches[1];
        }

        return '';
    }

    private function normalizeOperationNumber(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $value = trim((string) $raw);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[0-9]+(\.[0-9]+)?E[+\-]?[0-9]+$/i', $value) === 1) {
            return sprintf('%.0f', (float) $value);
        }

        return $value;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitPayer(string $payerRaw): array
    {
        if ($payerRaw === '') {
            return [null, null];
        }

        if (preg_match('/^(\+?\d[\d\s\-()]{8,})\s+(.*)$/u', $payerRaw, $matches) === 1) {
            $phone = preg_replace('/\s+/', '', $matches[1]);
            $name = trim($matches[2]);

            return [$phone !== '' ? $phone : null, $name !== '' ? $name : null];
        }

        return [null, $payerRaw];
    }
}
