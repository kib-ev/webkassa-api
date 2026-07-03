<?php

declare(strict_types=1);

namespace WebKassa\Client;

use JsonException;
use WebKassa\Config\WebKassaConfig;
use WebKassa\Contracts\WebKassaClientInterface;
use WebKassa\Exceptions\WebKassaApiException;
use WebKassa\Exceptions\WebKassaException;

final class WebKassaClient implements WebKassaClientInterface
{
    public function __construct(
        private readonly WebKassaConfig $config,
    ) {
        if ($this->config->token === '') {
            throw new WebKassaException('WebKassa API token is not configured.');
        }
    }

    /**
     * @param array<string, mixed>|object|null $body
     * @return array<int, array<string, mixed>>
     */
    public function post(string $path, array|object|null $body = null): array
    {
        $body ??= new \stdClass();

        try {
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $e) {
            throw new WebKassaException('Failed to encode request body: ' . $e->getMessage(), 0, $e);
        }

        $url = $this->config->baseUrl . '/' . ltrim($path, '/');

        $ch = curl_init($url);
        if ($ch === false) {
            throw new WebKassaException('Failed to initialize cURL.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $this->config->token,
                'Content-Type: application/json;charset=UTF-8',
                'Accept: application/json;charset=UTF-8',
            ],
            CURLOPT_TIMEOUT => $this->config->timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            throw new WebKassaException('WebKassa request failed: ' . $error);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new WebKassaApiException(
                sprintf('WebKassa API %s returned HTTP %d.', $path, $httpCode),
                $path,
                $httpCode,
                $response,
            );
        }

        try {
            $data = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new WebKassaException('Invalid JSON response from WebKassa.', 0, $e);
        }

        if (! is_array($data)) {
            throw new WebKassaException('Unexpected response format from WebKassa.');
        }

        return $data;
    }

    public function getEposPayReport(int $paymentDateFromMs, int $paymentDateToMs): array
    {
        return $this->post('epos-report/pay-report', [
            'paymentDateFrom' => $paymentDateFromMs,
            'paymentDateTo' => $paymentDateToMs,
        ]);
    }

    public function getEposInvoices(array|object|null $filters = null): array
    {
        return $this->post('epos-invoice/list', $filters ?? new \stdClass());
    }
}
