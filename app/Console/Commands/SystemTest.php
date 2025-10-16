<?php

namespace App\Console\Commands;

use App\Jobs\ipmiSensorJob;
use Illuminate\Console\Command;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;

class SystemTest extends Command
{
    use DispatchesJobs;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'system:test {name?} {--limit=3}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command test toàn bộ tính năng cơ bản';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. In thông tin
        $this->info('=== Bắt đầu kiểm tra hệ thống test ===');
        $this->newLine();
        $this->warn('Đây là cảnh báo mẫu');
        $this->newLine();
        $this->error('Đây là thông báo lỗi mẫu');
        $this->newLine();
        $this->line('Đây là một dòng bình thường');
        $this->newLine();
        $this->comment('Đây là thông báo bình thường');
        $this->newLine();

        // 2. Prompt
        $name = $this->argument('name') ?? $this->ask('Nhập tên của bạn');
        $this->clean();
        // $pass = $this->secret('Nhập mật khẩu (ẩn)');
        // $confirm = $this->confirm('Bạn chắc chắn muốn tiếp tục?');

        // if (!$confirm) {
        //     $this->warn('Đã huỷ thao tác');
        //     return 1;
        // };

        // $mode = $this->choice('Chọn chế độ chạy: ', ['Bình thường', 'Chi tiết', 'Im lặng'], 0);
        $limit = $this->option('limit');
        // $this->info("Xin chào $name! Bạn đã chọn chế độ $mode với giới hạn $limit");
        $this->newLine(2);

        // 3. Gọi command khác
        $this->line('Đang chạy cache:clear (một command khác)');
        $this->call('cache:clear');
        $this->newLine(2);

        // 4. Chạy trực tiếp shell (không khuyến khích, nên chạy nó trên jobs)
        $this->line('Đang chạy shell `whoami` trực tiếp');
        $user = trim(shell_exec('Whoami'));
        $this->line('Người dùng hiện tại: ' . $user);
        $this->newLine(2);

        // 5. Hiển thị bảng bằng cmd
        $headers = ['#', 'Host', 'Status'];
        $rows = [
            [1, 'localhost', 'OK'],
            [2, 'google.com', 'OK'],
            [3, 'facebook.com', 'OK'],
        ];
        $this->table($headers, $rows);
        $this->ansiTerminalCommand();
        $this->newLine(2);
        $this->pause();

        // 6. Thanh tiến trình
        $host = ['10.0.0.1', '10.0.0.2', '10.0.0.3'];
        $this->clean();
        $this->info('Đang xử lý tiến trình...');
        $this->withProgressBar($host, function ($host) {
            $this->comment("\n[PROGRESSING..] Đang xử lý $host");
            $this->line("\nĐang xử lý tiến trình...");
            usleep(2200000);
            $this->clean();

            // Sau khi xong callback, nó sẽ gọi hàm advance() để vẽ lại bar tự động
            // Có code trong callback sẽ overwrite cơ chế tự clean của withProgressBar -> tự clean lại.
        });
        $this->success("\n[SUCCESS] Đã xử lý xong");

        $this->newLine(2);
        $this->info('Hoàn tất xử lí');

        // 7. Đưa vào hàng chờ 1 job
        for ($i = 0; $i < 10; $i++) {
            $this->dispatch(new ipmiSensorJob())->onQueue("process1");
        }
        $this->success('Đã xong process 1');
        for ($i = 0; $i < 5; $i++) {
            $this->dispatch(new ipmiSensorJob())->onQueue("process2");
        }
        $this->success('Đã xong process 2');

        for ($i = 0; $i < 20; $i++) {
            $this->dispatch(new ipmiSensorJob())->onQueue("process3");
        }
        $this->success('Đã xong process 3');
        for ($i = 0; $i < 15; $i++) {
            $this->dispatch(new ipmiSensorJob())->onQueue("process4");
        }
        $this->success('Đã xong process 4');
        usleep(1000000);
        $this->success('Đã xong toàn bộ worker');
        $this->pause();

        // 7. Trả một exit code
        $this->info('=== Kết thúc luồng ===');
        return 0;
    }
    protected function success(string $message)
    {
        $this->line("<fg=green>{$message}</>");
    }
    protected function clean()
    {
        $this->output->write("\033c"); // reset screen (ANSI)
    }
    protected function ansiTerminalCommand()
    {
        $header = ['#', 'Mã ANSI (dùng $this->output->write("Mã") để dùng', 'Mô tả'];
        $row = [
            [1, '\033[2J', 'Xóa toàn bộ màn hình'],
            [2, '\033[H', 'Đưa con trỏ về góc trên trái'],
            [3, '\033[2J\033[H', 'Xóa toàn bộ + về đầu (clear chuẩn hơn)'],
            [4, '\033c', 'Reset toàn bộ terminal (như vừa mở mới)'],
            [5, '\033[31m', 'Đổi màu chữ sang đỏ'],
            [6, '\033[32m', 'Đổi màu chữ sang xanh lá'],
            [7, '\033[0m', 'Reset màu về mặc định'],
        ];
        $this->table($header, $row);
    }
    protected function pause()
    {
        $this->comment('fget(STDIN) là chuẩn console input...');
        fgets(STDIN);
    }
}
