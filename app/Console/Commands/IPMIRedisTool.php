<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class IPMIRedisTool extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'redis:tool
        {action : list|get|del|keys|flush|ttl}
        {key? : Tên key (tuỳ theo action)}';

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
        $action = $this->argument('action');
        $key = $this->argument('key');

        switch ($action) {
            case 'list':
            case 'keys':
                $this->listKeys();
                break;

            case 'get':
                if (!$key) {
                    $this->warn('Cần nhập tên key. Ví dụ: php artisan redis:tool get ipmi:sensor:host_10_0_0_1');
                    return;
                }
                $value = Redis::get($key);
                if ($value === null) {
                    $this->warn("Key '$key' không tồn tại.");
                } else {
                    $this->info("Giá trị của '$key':");
                    $this->line($value);
                }
                break;

            case 'ttl':
                if (!$key) {
                    $this->warn('Cần nhập tên key. Ví dụ: php artisan redis:tool ttl mykey');
                    return;
                }
                $ttl = Redis::ttl($key);
                $this->info("TTL của '$key': " . ($ttl >= 0 ? "$ttl giây" : "vô hạn hoặc key không tồn tại"));
                break;

            case 'del':
                if (!$key) {
                    $this->warn('Cần nhập tên key. Ví dụ: php artisan redis:tool del ipmi:sensor:host_10_0_0_1');
                    return;
                }
                $deleted = Redis::del($key);
                if ($deleted) {
                    $this->info("Đã xoá key '$key'");
                } else {
                    $this->warn("Key '$key' không tồn tại hoặc không xoá được.");
                }
                break;

            case 'flush':
                if (!$this->confirm('Bạn có chắc muốn xoá toàn bộ Redis (FLUSHALL)?', false)) {
                    $this->warn('Đã huỷ thao tác.');
                    return;
                }
                Redis::flushall();
                $this->info('Đã xoá toàn bộ dữ liệu trong Redis.');
                break;

            default:
                $this->warn("Action không hợp lệ: $action");
                $this->line("Hỗ trợ: list, get, del, ttl, flush");
        }
    }

    protected function listKeys()
    {
        $this->info('📋 Danh sách key trong Redis:');
        $keys = Redis::keys('*');

        if (empty($keys)) {
            $this->warn('Redis trống (không có key nào).');
            return;
        }

        foreach ($keys as $i => $k) {
            $this->line(($i + 1) . '. ' . $k);
        }

        $this->info("\nTổng cộng: " . count($keys) . " key(s).");
    }
}
