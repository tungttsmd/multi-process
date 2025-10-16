<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class IPMIClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:cls';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Xoá mọi cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->call('cache:clear');
        $this->call('config:clear');
        $this->call('route:clear');
        $this->call('view:clear');
    }
}
