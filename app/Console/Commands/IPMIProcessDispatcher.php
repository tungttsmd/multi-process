<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class IPMIProcessDispatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:process {--a=}';

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
        $action = $this->option('a');
        if ($action === 'run') {
        } elseif ($action === 'list') {
            // Trên Windows: cần gọi thông qua cmd /c
            $cmd = "wmic process where (name='php.exe' and CommandLine like '%queue:work%') get ProcessId,CommandLine";
            $p = new Process(['cmd', '/c', $cmd]);
            $p->run();
            // In ra output gọn gàng
            $output = print_r($p->getOutput());
            if (!$output) {
                $this->warn("Không có tiến trình queue:work nào đang chạy.");
            } else {
                $this->info("Danh sách worker đang chạy:");
                $this->line($output);
            }
        } elseif ($action === 'reset') {
            $process = new Process([
                PHP_BINARY,
                'artisan',
                'queue:restart',
            ]);
            $process->run();
            $this->info('Đã kill toàn bộ processor');
            $this->info('=== Đang tiến hành khởi động lại ===');
            $process = new Process([
                PHP_BINARY,
                'artisan',
                'ipmi:proces --run',
            ]);
            $this->info('Đã khởi động lại thành công toàn bộ processor');
            return;
        } elseif ($action === 'kill') {
            $process = new Process([
                PHP_BINARY,
                'artisan',
                'queue:restart',
            ]);
            $process->run();
            $this->info('Đã kill toàn bộ processor');
            return;
        } else {
            $this->error('Action không hợp lệ (run/list)');
            return;
        }
    }
}
