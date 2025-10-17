<?php

namespace App\Console\Commands;

use App\Jobs\RedisToDatabaseFlusher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IPMIRedisDispatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:save {--h=}';
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
                $this->line(ENV('QUEUE_UPDATES', 'default'));
                // Chưa sửa dược lỗi vì sao config() không chạy được -> dùng tạm ENV
                dispatch(new RedisToDatabaseFlusher($item->ip))->onQueue(ENV('QUEUE_UPDATES', 'default'));
            }
        }
    }
}
