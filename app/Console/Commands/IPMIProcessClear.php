<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Process\Process;

class IPMIProcessClear extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info(" Chuẩn bị xoá toàn bộ cache hệ thống...");

        if (!$this->confirm('Bạn có chắc muốn xoá toàn bộ cache (config, route, view, Redis, queue)?', true)) {
            $this->warn('Đã huỷ thao tác xoá cache.');
            return;
        }

        // Xoá cache Laravel
        $this->section('Laravel cache');
        Artisan::call('cache:clear');
        $this->line(Artisan::output());

        $this->section('Config cache');
        Artisan::call('config:clear');
        $this->line(Artisan::output());

        $this->section('Route cache');
        Artisan::call('route:clear');
        $this->line(Artisan::output());

        $this->section('View cache');
        Artisan::call('view:clear');
        $this->line(Artisan::output());

        // Xoá queue cache (nếu có)
        $this->section('Queue cache');
        Artisan::call('queue:clear');
        $this->line(Artisan::output());

        // Xoá Redis cache (nếu bạn đang dùng Redis để lưu dữ liệu IPMI)
        $this->section('Redis cache');
        $process = Process::fromShellCommandline('redis-cli FLUSHALL');
        $process->run();

        if ($process->isSuccessful()) {
            $this->info(' Đã xoá toàn bộ Redis cache.');
        } else {
            $this->warn('Không thể xoá Redis cache (kiểm tra redis-cli).');
        }

        $this->newLine(2);
        $this->info('Toàn bộ cache hệ thống đã được xoá sạch!');
    }

    protected function section(string $title)
    {
        $this->newLine();
        $this->info("Đang xoá {$title}...");
    }
}
