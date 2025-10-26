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
    protected $signature = 'ipmi:redis {mode : all|host:<ip>}';
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
        $mode = $this->argument('mode');

        if ($mode === 'all') {
            $list = DB::table('hosts')->get();
            foreach ($list as $index => $item) {
                dispatch(new RedisToDatabase($item->ip))->onQueue('processor_update_' . (($index % 4) + 1));
            }
        }

        if (str_starts_with($mode, 'host:')) {
            $ip = substr($mode, 5);
            $host = DB::table('hosts')->where('ip', $ip)->first();

            if (!$host) {
                $this->error("Không tìm thấy host có IP: {$ip}");
                return;
            }

            $processor = config('queue.processor.user_update');
            $this->info(json_encode($processor));

            dispatch(new RedisToDatabase($ip))
            ->onQueue($processor);
        }

    }
}
