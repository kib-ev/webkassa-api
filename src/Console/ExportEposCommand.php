<?php

declare(strict_types=1);

namespace WebKassa\Console;

use Illuminate\Console\Command;
use InvalidArgumentException;
use WebKassa\Support\PeriodResolver;
use WebKassa\WebKassaManager;

final class ExportEposCommand extends Command
{
    protected $signature = 'webkassa:export-epos
                            {--month= : Calendar month (YYYY-MM)}
                            {--from= : Period start (YYYY-MM-DD)}
                            {--to= : Period end (YYYY-MM-DD)}
                            {--output= : Output xlsx path}';

    protected $description = 'Export e-pos payment report from WebKassa to Excel';

    public function handle(WebKassaManager $webkassa): int
    {
        $timezone = (string) config('webkassa.timezone', 'Europe/Minsk');

        try {
            $resolver = new PeriodResolver($timezone);
            [$from, $to] = $resolver->resolve([
                'month' => $this->option('month'),
                'from' => $this->option('from'),
                'to' => $this->option('to'),
            ]);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $output = $this->option('output')
            ?? config('webkassa.epos.export_path') . '/' . $from->format('Y-m') . '.xlsx';

        $this->info(sprintf(
            'Exporting e-pos report for %s — %s...',
            $from->format('d.m.Y'),
            $to->format('d.m.Y'),
        ));

        $count = $webkassa->exportEposReport($from, $to, $output);

        $this->info(sprintf('Done: %d records → %s', $count, $output));

        return self::SUCCESS;
    }
}
