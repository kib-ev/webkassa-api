<?php

declare(strict_types=1);

namespace WebKassa\Contracts;

interface WebKassaClientInterface
{
    /**
     * @param array<string, mixed>|object|null $body
     * @return array<int, array<string, mixed>>
     */
    public function post(string $path, array|object|null $body = null): array;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEposPayReport(int $paymentDateFromMs, int $paymentDateToMs): array;

    /**
     * @param array<string, mixed>|object|null $filters
     * @return array<int, array<string, mixed>>
     */
    public function getEposInvoices(array|object|null $filters = null): array;
}
