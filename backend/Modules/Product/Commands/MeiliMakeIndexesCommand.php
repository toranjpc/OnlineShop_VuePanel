<?php

namespace Modules\Product\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Modules\Product\Models\Bijac;

class MeiliMakeIndexesCommand extends Command
{
    // php artisan meili:refreshdb --days=4 
    protected $signature = 'meili:refreshdb {--days=4 : Only index bijacs with bijac_date newer than this many days ago (subDays)}';

    protected $description = 'Flush Bijac from Scout, sync Meili index settings, then index only recent bijacs (default: last 4 days)';

    public function handle(): int
    {
        $modelClass = Bijac::class;
        $days = max(0, (int) $this->option('days'));
        $cutoffDate = Carbon::now()->subDays($days);
        $chunkSize = (int) config('scout.chunk.searchable', 500);

        $this->info('Running scout:flush for Bijac...');
        Artisan::call('scout:flush', ['model' => $modelClass]);
        $this->output->write(Artisan::output());

        $this->info('Running scout:sync-index-settings...');
        Artisan::call('scout:sync-index-settings');
        $this->output->write(Artisan::output());

        $this->info("Indexing Bijac records with bijac_date > {$cutoffDate->toDateTimeString()} (--days={$days}, chunk size {$chunkSize})...");

        $count = 0;
        Bijac::query()
            ->where('bijac_date', '>', $cutoffDate)
            ->orderBy('id')
            ->chunk($chunkSize, function ($chunk) use (&$count) {
                if ($chunk->isNotEmpty()) {
                    $chunk->searchableSync();
                    $count += $chunk->count();
                }
            });

        $this->info("Indexed {$count} Bijac document(s) into MeiliSearch.");
        $this->info('Done.');

        return self::SUCCESS;
    }
}
