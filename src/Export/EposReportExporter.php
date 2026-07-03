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
use WebKassa\Support\EposPaymentNormalizer;

final class EposReportExporter
{
    private readonly EposPaymentNormalizer $normalizer;

    public function __construct(
        private readonly WebKassaClientInterface $client,
        private readonly WebKassaConfig $config,
    ) {
        $this->normalizer = new EposPaymentNormalizer($this->config);
    }

    public function exportPeriod(DateTimeInterface $from, DateTimeInterface $to, string $outputPath): int
    {
        $fromImmutable = DateTimeImmutable::createFromInterface($from);
        $toImmutable = DateTimeImmutable::createFromInterface($to);
        $tz = new DateTimeZone($this->config->timezone);

        $fromLocal = new DateTimeImmutable($fromImmutable->format('Y-m-d') . ' 00:00:00', $tz);
        $toLocal = new DateTimeImmutable($toImmutable->format('Y-m-d') . ' 23:59:59', $tz);

        $payments = $this->client->getEposPayReport(
            $fromLocal->getTimestamp() * 1000,
            $toLocal->getTimestamp() * 1000 + 999,
        );

        $invoiceMap = $this->normalizer->buildInvoiceMap($this->client->getEposInvoices());
        $rows = $this->normalizer->normalizePayments($payments, $invoiceMap);

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

        foreach ($rows as $row) {
            $sheet->fromArray(
                [
                    $row['payment_method'],
                    $row['amount'],
                    $row['net_amount'],
                    $row['paid_at'],
                    $row['operation_number'],
                    $row['epos_service'],
                    $row['payer_raw'],
                    $row['invoice_created_at'],
                    $row['account_number'],
                    '',
                    '',
                ],
                null,
                'B' . $dataRow,
            );

            $totalSum += (float) $row['amount'];
            $dataRow++;
        }

        $sheet->setCellValue('B10', 'Итоговая сумма');
        $sheet->setCellValue('C10', round($totalSum, 2));

        $this->saveSpreadsheet($spreadsheet, $outputPath);

        return count($rows);
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
