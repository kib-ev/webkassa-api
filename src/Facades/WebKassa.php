<?php

declare(strict_types=1);

namespace WebKassa\Facades;

use Illuminate\Support\Facades\Facade;
use WebKassa\WebKassaManager;

/**
 * @method static \WebKassa\Config\WebKassaConfig config()
 * @method static \WebKassa\Contracts\WebKassaClientInterface client()
 * @method static array<int, array<string, mixed>> eposPayReport(\DateTimeInterface $from, \DateTimeInterface $to)
 * @method static array<int, array<string, mixed>> eposInvoices(array|object|null $filters = null)
 * @method static int exportEposReport(\DateTimeInterface $from, \DateTimeInterface $to, string $outputPath)
 * @method static \WebKassa\Export\EposReportExporter eposReportExporter()
 *
 * @see \WebKassa\WebKassaManager
 */
final class WebKassa extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return WebKassaManager::class;
    }
}
