<?php

namespace App\Console\Commands;

use App\Services\QueueMonitorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Illuminate\Support\Facades\Schema;

class QueueMonitorCommand extends Command
{
    protected $signature = 'monitor:queue {action? : view|clear|stop|start|toggle|start-all|stop-all} {queue?}';
    protected $description = 'Theo dõi và quản lý các queue đang hoạt động';

    public function handle()
    {
        $io = new SymfonyStyle($this->input, $this->output);

        if (!$this->checkEnvironment($io)) {
            return;
        }

        $queues = array_merge(
            config('queue.processor.sensor'),
            config('queue.processor.power'),
            config('queue.processor.update'),
            config('queue.processor.execute'),
            [
                config('queue.processor.user_sensor'),
                config('queue.processor.user_power'),
                config('queue.processor.user_update'),
                config('queue.processor.user_execute'),
            ]
        );

        $monitor = new QueueMonitorService($queues);

        // Hiển thị menu
        $actions = [
            '1' => 'Xem trạng thái',
            '2' => 'Bật/Tắt queue',
            '3' => 'Bật toàn bộ queue',
            '4' => 'Đóng toàn bộ queue',
            '5' => 'Xóa job (1 hoặc toàn bộ)',
            '6' => 'Làm sạch toàn bộ hệ thống queue',
            '0' => 'Thoát chương trình',
        ];

        $io->writeln('');
        $io->writeln('<fg=cyan>Chọn thao tác (nhập số):</>');

        foreach ($actions as $key => $label) {
            $color = match ($key) {
                '0' => 'yellow',
                '6' => 'red',
                default => 'green'
            };
            $io->writeln("  [<fg={$color}>{$key}</>] {$label}");
        }

        $io->write(' > ');
        $choice = trim(fgets(STDIN)); // nhận input người dùng

        if (!array_key_exists($choice, $actions)) {
            $io->error('Lựa chọn không hợp lệ!');
            return;
        }

        if ($choice === '0') {
            $io->writeln('<fg=yellow>Đã thoát chương trình.</>');
            return;
        }

        $action = $actions[$choice];
        $io->newLine();

        // Gọi action tương ứng
        match ($action) {
            'Xem trạng thái'               => $this->showQueueStatus($io, $monitor),
            'Bật/Tắt queue'                => $this->toggleQueue($io, $queues),
            'Bật toàn bộ queue'            => $this->startAllQueues($io, $queues),
            'Đóng toàn bộ queue'           => $this->stopAllQueues($io, $queues),
            'Xóa job (1 hoặc toàn bộ)'     => $this->clearQueueMenu($io, $queues),
            'Làm sạch toàn bộ hệ thống queue' => $this->resetAllQueues($io, $queues),
        };
    }

    // ================================================================
    // 1️⃣ HIỂN THỊ TRẠNG THÁI
    // ================================================================
    protected function showQueueStatus(SymfonyStyle $io, QueueMonitorService $monitor): void
    {
        do {
            // Xóa màn hình cho sạch (Windows-friendly)
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                system('cls');
            } else {
                system('clear');
            }

            $io->title('Trạng thái hiện tại của Queue Worker');

            $statuses = $monitor->getQueueStatus();
            $rows = [];

            foreach ($statuses as $queue => $info) {
                $isRunning = $this->isQueueRunning($queue);
                $time = Cache::get("queue_running:{$queue}");
                $done = Cache::get("queue_done:{$queue}", 0);

                $rows[] = [
                    "<fg=cyan>{$queue}</>",
                    $info['pending'],
                    $info['failed'],
                    $done,
                    $isRunning ? '<fg=green>Bật</>' : '<fg=red>Tắt</>',
                    $time ? $time->format('H:i:s') : '-',
                ];
            }

            $io->table(['Queue', 'Pending', 'Failed', 'Done', 'Trạng thái', 'Bật từ'], $rows);

            $io->writeln('');
            $io->writeln('<fg=yellow>[1]</> Làm mới dữ liệu    <fg=cyan>[0]</> Quay lại menu chính');
            $io->write(' > ');

            $choice = trim(fgets(STDIN));

        } while ($choice === '1');

        $io->newLine();
        $io->writeln('<fg=yellow>Quay lại menu chính...</>');
        sleep(1);
    }



    // ================================================================
    // 2️⃣ BẬT / TẮT QUEUE
    // ================================================================
    protected function toggleQueue(SymfonyStyle $io, array $queues): void
    {
        $queue = $io->choice('Chọn queue cần bật/tắt', $queues);
        $isRunning = $this->isQueueRunning($queue);

        if ($isRunning) {
            $this->stopQueue($queue);
            $io->success("Đã tắt worker queue: {$queue}");
        } else {
            $this->startQueue($queue);
            $io->success("Đã bật worker queue: {$queue}");
        }
    }

    // ================================================================
    // 3️⃣ BẬT TOÀN BỘ QUEUE
    // ================================================================
    protected function startAllQueues(SymfonyStyle $io, array $queues): void
    {
        $io->section('Đang bật toàn bộ queue...');
        foreach ($queues as $queue) {
            if (!$this->isQueueRunning($queue)) {
                $this->startQueue($queue);
                $io->writeln("<fg=green>Đã bật: {$queue}</>");
                usleep(100000); // 0.3s để tránh dồn process
            } else {
                $io->writeln("<fg=yellow>Bỏ qua (đang chạy): {$queue}</>");
            }
        }
        $io->success('Hoàn tất bật toàn bộ queue.');
    }

    // ================================================================
    // 4️⃣ DỪNG TOÀN BỘ QUEUE
    // ================================================================
    protected function stopAllQueues(SymfonyStyle $io, array $queues): void
    {
        $io->section('Đang dừng toàn bộ queue...');
        foreach ($queues as $queue) {
            $this->stopQueue($queue);
            $io->writeln("<fg=red>Đã dừng: {$queue}</>");
        }
        $io->success('Hoàn tất dừng tất cả queue.');
    }

    // ================================================================
    // 5️⃣ CLEAR JOBS (1 hoặc tất cả)
    // ================================================================
    protected function clearQueueMenu(SymfonyStyle $io, array $queues): void
    {
        $choice = $io->choice('Chọn kiểu xóa:', [
            'Một queue cụ thể',
            'Toàn bộ queue'
        ]);

        if ($choice === 'Một queue cụ thể') {
            $queue = $io->choice('Chọn queue cần clear', $queues);
            $this->clearQueue($io, $queue);
        } else {
            foreach ($queues as $queue) {
                $this->clearQueue($io, $queue);
            }
        }

        $io->success('Hoàn tất xóa job.');
    }

    // ================================================================
    // 6️⃣ CLEAR JOB TRONG QUEUE
    // ================================================================
    protected function clearQueue(SymfonyStyle $io, string $queue): void
    {
        $driver = config('queue.default');
        $io->writeln("<fg=yellow>Đang xóa job trong queue: {$queue} (driver={$driver})...</>");

        if ($driver === 'database') {
            DB::table('jobs')->where('queue', $queue)->delete();
        } else {
            Redis::del("queues:{$queue}");
        }

        $io->writeln("<fg=green>Đã xóa tất cả job trong queue: {$queue}</>");
    }

    // ================================================================
    // 7️⃣ HÀM START / STOP / CHECK
    // ================================================================
    protected function startQueue(string $queue): void
    {
        $cmd = 'powershell -NoProfile -ExecutionPolicy Bypass -Command "Start-Process php -ArgumentList \'artisan queue:work --queue=' . $queue . ' --sleep=1\' -WindowStyle Hidden"';
        $process = Process::fromShellCommandline($cmd);
        $process->run();

        Cache::put("queue_running:{$queue}", now(), 3600);
    }

    protected function resetAllQueues(SymfonyStyle $io, array $queues): void
    {
        $io->section('  Làm sạch toàn bộ job/cache và đóng toàn bộ queue');
        if (!$io->confirm('Bạn có chắc chắn muốn dừng tất cả worker và xóa mọi dữ liệu queue (pending + failed + done + cache)?', false)) {
            $io->warning('Hủy thao tác.');
            return;
        }

        // 1️⃣ Dừng toàn bộ process queue
        $io->writeln('<fg=red>Dừng toàn bộ tiến trình queue:work ...</>');
        exec('wmic process where "CommandLine like \'%queue:work%\'" delete');

        // 2️⃣ Xóa job trong bảng jobs, failed_jobs và done (nếu có bảng archived)
        $io->writeln('<fg=yellow>Xóa job trong bảng jobs, failed_jobs, done_jobs (nếu có)...</>');

        if (Schema::hasTable('jobs')) DB::table('jobs')->truncate();
        if (Schema::hasTable('failed_jobs')) DB::table('failed_jobs')->truncate();
        if (Schema::hasTable('completed_jobs')) DB::table('completed_jobs')->truncate();

        // 3️⃣ Xóa toàn bộ key Redis liên quan đến queue
        $io->writeln('<fg=yellow>Xóa toàn bộ key Redis liên quan đến queue...</>');
        $keys = Redis::keys('*queue*');
        foreach ($keys as $key) {
            Redis::del($key);
        }

        // 4️⃣ Xóa cache queue_running
        $io->writeln('<fg=yellow>Xóa cache queue_running:*</>');
        $cacheKeys = Redis::keys('laravel_cache_queue_running:*');
        foreach ($cacheKeys as $key) {
            Redis::del($key);
        }

        // Xóa cache queue_done:
        $io->writeln('<fg=yellow>Xóa cache queue_done:*</>');
        $doneKeys = Redis::keys('laravel_cache:queue_done:*');
        foreach ($doneKeys as $key) {
            Redis::del($key);
        }

        // Xóa cache queue_done:
        $io->writeln('<fg=yellow>Xóa cache laravel_cache_processor_*</>');
        $doneKeys = Redis::keys('laravel_cache_processor_*');
        foreach ($doneKeys as $key) {
            Redis::del($key);
        }

        $io->newLine();
        $io->success('✅ Đã làm sạch toàn bộ hệ thống queue — tất cả job, cache, tiến trình đã bị xóa hoàn toàn.');
    }

    protected function stopQueue(string $queue): void
    {
        $cmd = "wmic process where \"CommandLine like '%--queue={$queue}%' and CommandLine like '%queue:work%'\" delete";
        exec($cmd);

        Cache::forget("queue_running:{$queue}");
    }

    protected function checkEnvironment(SymfonyStyle $io): bool
    {
        $io->section('[START] Kiểm tra môi trường hệ thống...');

        // 1️⃣ Kiểm tra Redis
        try {
            $ping = Redis::ping();
            if (strtolower($ping) !== 'pong') {
                throw new \Exception('Không phản hồi PONG từ Redis.');
            }
            $io->writeln('<fg=green>[OK] Redis đang hoạt động.</>');
        } catch (\Throwable $e) {
            $io->error('[FAIL] Không thể kết nối tới Redis. Hãy kiểm tra Redis Server.');
            $io->writeln('<fg=yellow>Gợi ý: hãy mở Redis bằng lệnh "redis-server.exe" hoặc script redis-install.bat.</>');
            return false;
        }

        // 2️⃣ Kiểm tra MySQL (WAMP)
        try {
            DB::select('SELECT 1');
            $io->writeln('<fg=green>[OK] SQL đang hoạt động.</>');
        } catch (\Throwable $e) {
            $io->error('[FAIL] Không thể kết nối tới SQL. Có thể WAMP chưa chạy.');
            $io->writeln('<fg=yellow>Gợi ý: bật WAMP hoặc MySQL service trước khi chạy monitor:queue.</>');
            return false;
        }

        $io->success('Môi trường hệ thống ổn định — có thể tiếp tục.');
        $io->newLine();

        return true;
    }

    protected function isQueueRunning(string $queue): bool
    {
        return Cache::has("queue_running:{$queue}");
    }
}
