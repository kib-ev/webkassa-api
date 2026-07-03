<?php

declare(strict_types=1);

namespace WebKassa\Export;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use WebKassa\Config\WebKassaConfig;
use WebKassa\Contracts\WebKassaClientInterface;
use WebKassa\Exceptions\WebKassaException;

final class EposReportExporter
{
    private readonly DateTimeZone $timezone;

    public function __construct(
        private readonly WebKassaClientInterface $client,
        private readonly WebKassaConfig $config,
    ) {
        $this->timezone = new DateTimeZone($this->config->timezone);
    }

    public function exportPeriod(DateTimeInterface $from, DateTimeInterface $to, string $outputPath): int
    {
        $fromImmutable = DateTimeImmutable::createFromInterface($from);
        $toImmutable = DateTimeImmutable::createFromInterface($to);

        $utc = new DateTimeZone('UTC');
        $fromUtc = new DateTimeImmutable($fromImmutable->format('Y-m-d') . ' 00:00:00', $utc);
        $toUtc = new DateTimeImmutable($toImmutable->format('Y-m-d') . ' 23:59:59', $utc);

        $payments = $this->client->getEposPayReport(
            $fromUtc->getTimestamp() * 1000,
            $toUtc->getTimestamp() * 1000 + 999,
        );

        $invoiceMap = $this->buildInvoiceMap();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Лист1');

        $sheet->setCellValue('B2', 'Аналитические отчеты. Отчет по платежам e-pos (Общий). ');
        $sheet->setCellValue(
            'B3',
            sprintf(
                'Абонент:  Абонент:%s,,  УНП  УНП: %s',
                $this->config->merchantName,
                $this->config->merchantUnp,
            ),
        );
        $sheet->setCellValue(
            'B5',
            sprintf(
                'Период: Период: с %s до %s ',
                $fromImmutable->format('d.m.Y'),
                $toImmutable->format('d.m.Y'),
            ),
        );
        $sheet->setCellValue('B6', 'Тип отчета: Тип отчета: общий');
        $sheet->setCellValue('B7', 'Валюта: Валюта: BYN');

        $headers = [
            'Способ оплаты',
            'Сумма BYN',
            'Сумма к зачислению ',
            'Дата оплаты',
            'Номер транзакции',
            'Услуга E-POS',
            'Плательщик',
            'Дата счета',
            'Лицевой счет',
            'SN СКО',
            'Рег № ПК',
        ];

        $headerRow = 14;
        $sheet->fromArray($headers, null, 'B' . $headerRow);

        $totalSum = 0.0;
        $dataRow = $headerRow + 1;

        foreach ($payments as $payment) {
            $invoiceId = (int) ($payment['invoiceId'] ?? 0);
            $invoice = $invoiceMap[$invoiceId] ?? null;

            $sheet->fromArray(
                [
                    $this->formatPaymentType((string) ($payment['preDocTypeString'] ?? '')),
                    (float) ($payment['totalSum'] ?? 0),
                    (float) ($payment['transferAmount'] ?? 0),
                    $this->formatTimestamp((int) ($payment['paymentDate'] ?? 0)),
                    $payment['transactionId'] ?? '',
                    (string) ($invoice['eposService'] ?? $this->config->defaultEposService),
                    (string) ($payment['payerName'] ?? ''),
                    isset($invoice['invoiceDate'])
                        ? $this->formatTimestamp((int) $invoice['invoiceDate'])
                        : '',
                    $invoice['invoiceNumber'] ?? $this->extractAccountNumber((string) ($payment['fullEposNumber'] ?? '')),
                    '',
                    '',
                ],
                null,
                'B' . $dataRow,
            );

            $totalSum += (float) ($payment['totalSum'] ?? 0);
            $dataRow++;
        }

        $sheet->setCellValue('B10', 'Итоговая сумма');
        $sheet->setCellValue('C10', round($totalSum, 2));

        $this->saveSpreadsheet($spreadsheet, $outputPath);

        return count($payments);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildInvoiceMap(): array
    {
        $map = [];

        foreach ($this->client->getEposInvoices() as $invoice) {
            $invoiceId = (int) ($invoice['invoiceId'] ?? 0);
            if ($invoiceId > 0) {
                $map[$invoiceId] = $invoice;
            }
        }

        return $map;
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

    private function formatTimestamp(int $timestampMs): string
    {
        if ($timestampMs <= 0) {
            return '';
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

    private function saveSpreadsheet(Spreadsheet $spreadsheet, string $outputPath): void
    {
        $directory = dirname($outputPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new WebKassaException('Failed to create directory: ' . $directory);
        }

        (new Xlsx($spreadsheet))->save($outputPath);
    }
}
