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
        $queues = config('queue.processor.update');
        foreach ($list as $item) {
            foreach ($queues as $queue) {
                $this->line($queue . ' => ' . $item->ip);
                dispatch(new RedisToDatabase($item->ip))->onQueue($queue);
            }
        }
    }
}
