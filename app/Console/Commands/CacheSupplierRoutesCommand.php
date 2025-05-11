<?php

namespace App\Console\Commands;

use App\Jobs\CacheSupplierRoutesJob;
use Illuminate\Console\Command;

class CacheSupplierRoutesCommand extends Command
{
    protected $signature = 'travelfusion:cache-routes';
    protected $description = 'Cache TravelFusion supplier routes';

    public function handle()
    {
        $this->info('Starting to cache TravelFusion supplier routes...');
        CacheSupplierRoutesJob::dispatch();
        $this->info('Job dispatched successfully!');
    }
} 