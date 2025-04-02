<?php

namespace App\Console\Commands;

use App\Services\WebMagicCrawlerService;
use Illuminate\Console\Command;

class ParseBlogCommand extends Command
{
    protected $signature = 'parse:blog';

    protected $description = 'Command description';

    public function handle(WebMagicCrawlerService $service): void
    {
        $service->run();
    }
}
