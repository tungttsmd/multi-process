<?php

namespace App\Console\Commands;

use App\Jobs\DatabaseUpdator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\DB;

class IPMIRedisUpdator extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:update {--h=}';
    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Lệnh đẩy Redis vào Database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $host = $this->option('h');
        if ($host === 'all') {
            $list = DB::table('hosts')->get();
            foreach ($list as $item) {
                $this->line($item->ip);
                dispatch(new DatabaseUpdator($item->ip))->onQueue(ENV('QUEUE_UPDATES', 'default'));
            }
        }
    }
}
