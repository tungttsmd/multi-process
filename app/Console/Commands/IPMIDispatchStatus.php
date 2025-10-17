<?php

namespace App\Console\Commands;

use App\Jobs\StatusCrawler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IPMIDispatchStatus extends Command
{
    protected $signature = 'ipmi:status {mode : all|host:<ip>}';
    protected $description = 'Dispatch job StatusCrawler cho toàn bộ host hoặc 1 host cụ thể';

    public function handle()
    {
        $mode = $this->argument('mode');

        // --- Nếu là all ---
        if ($mode === 'all') {
            $hosts = DB::table('hosts')->get();
            if ($hosts->isEmpty()) {
                $this->warn('Không có host nào trong bảng hosts.');
                return;
            }

            foreach ($hosts as $index => $host) {
                dispatch(new StatusCrawler($host->ip, $host->username, $host->password))
                    ->onQueue('processor_status_' . (($index % 8) + 1));
                $this->success("Đã dispatch StatusCrawler cho {$host->ip}");
            }

            $this->info("Đã dispatch tất cả (" . count($hosts) . " hosts).");
            return;
        }

        // --- Nếu là host:xxx ---
        if (str_starts_with($mode, 'host:')) {
            $ip = substr($mode, 5);
            $host = DB::table('hosts')->where('ip', $ip)->first();

            if (!$host) {
                $this->error("Không tìm thấy host có IP: {$ip}");
                return;
            }

            dispatch(new StatusCrawler($host->ip, $host->username, $host->password))
                ->onQueue('processor_status_1');

            $this->info("Đã dispatch StatusCrawler cho {$host->ip}");
            return;
        }

        $this->error('Sai cú pháp. Dùng: php artisan ipmi:status all hoặc php artisan ipmi:status host:192.168.1.10');
    }

    protected function success(string $message)
    {
        $this->line("<fg=green>{$message}</>");
    }
}
