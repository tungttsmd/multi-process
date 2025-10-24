<?php

namespace App\Console\Commands;

use App\Jobs\RedisToDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IPMIDispatchRedis extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:redis';
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
        $list = DB::table('hosts')->get();
        foreach ($list as $index => $item) {
            dispatch(new RedisToDatabase($item->ip))->onQueue('processor_update_' . (($index % 4) + 1));
        }
    }
}
