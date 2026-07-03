<?php

declare(strict_types=1);

namespace WebKassa;

use Illuminate\Support\ServiceProvider;
use WebKassa\Client\WebKassaClient;
use WebKassa\Config\WebKassaConfig;
use WebKassa\Console\ExportEposCommand;
use WebKassa\Contracts\WebKassaClientInterface;
use WebKassa\Export\EposReportExporter;

class WebKassaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/webkassa.php', 'webkassa');

        $this->app->singleton(WebKassaConfig::class, function ($app): WebKassaConfig {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('webkassa', []);

            return WebKassaConfig::fromArray($config);
        });

        $this->app->singleton(WebKassaClientInterface::class, function ($app): WebKassaClient {
            return new WebKassaClient($app->make(WebKassaConfig::class));
        });

        $this->app->alias(WebKassaClientInterface::class, WebKassaClient::class);

        $this->app->singleton(WebKassaManager::class, function ($app): WebKassaManager {
            return new WebKassaManager($app->make(WebKassaConfig::class));
        });

        $this->app->singleton(EposReportExporter::class, function ($app): EposReportExporter {
            return $app->make(WebKassaManager::class)->eposReportExporter();
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/webkassa.php' => config_path('webkassa.php'),
            ], 'webkassa-config');

            $this->commands([
                ExportEposCommand::class,
            ]);
        }
    }
}
