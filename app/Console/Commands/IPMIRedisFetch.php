<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;

class IPMIRedisFetch extends Command
{
    protected $signature = 'redis:fetch
        {action? : list (xem danh sách key) / all (xem toàn bộ keys + values) / <tên key> để xem value của tên key cụ thể}';

    protected $description = 'Xem dữ liệu Redis — có thể xem tất cả, 1 key, hoặc chỉ list key';

    public function handle()
    {
        $action = $this->argument('action');

        // --- Nếu không truyền gì: hiển thị toàn bộ key + value ---
        if (!$action) {
            $this->error('action không hợp lệ (all/list/<key>)');
            return;
        }
        // --- Nếu không truyền gì: hiển thị toàn bộ key + value ---
        if ($action === 'all') {
            $this->fetchAll();
            return;
        }

        // --- Nếu action là "list": chỉ liệt kê key ---
        if ($action === 'list') {
            $this->showKeyList();
            return;
        }

        // --- Nếu không phải list: xem 1 key cụ thể ---
        $this->fetchSingleKey($action);
    }

    protected function fetchAll()
    {
        $keys = Redis::keys('*');

        if (empty($keys)) {
            $this->warn('Redis trống (không có key nào).');
            return;
        }

        $this->line('=== Danh sách toàn bộ key và giá trị trong Redis ===');
        $this->newLine();

        foreach ($keys as $i => $k) {
            $type = Redis::type($k);
            $value = $this->readKeyValue($k, $type);
            $short = $this->shortValue($value);

            $this->line(($i + 1) . ". {$k}");
            $this->line("   Loại: {$type}");
            $this->line("   Giá trị: {$short}");
            $this->newLine();
        }

        $this->info("Tổng cộng: " . count($keys) . " key(s).");
    }

    protected function showKeyList()
    {
        $keys = Redis::keys('*');
        if (empty($keys)) {
            $this->warn('Redis trống (không có key nào).');
            return;
        }

        $this->line('=== Danh sách key trong Redis ===');
        foreach ($keys as $i => $k) {
            $this->line(($i + 1) . ". " . $k);
        }
        $this->newLine();
        $this->info("Tổng cộng: " . count($keys) . " key(s).");
    }

    protected function fetchSingleKey(string $key)
    {
        $type = Redis::type($key);
        if ($type === 'none') {
            $this->error("Key '$key' không tồn tại.");
            return;
        }

        $value = $this->readKeyValue($key, $type);

        $this->line("=== Key: {$key} ===");
        $this->line("Loại: {$type}");
        $this->newLine();
        $this->line($value);
    }

    protected function readKeyValue(string $key, string $type)
    {
        switch ($type) {
            case 'string':
                return Redis::get($key);
            case 'list':
                return json_encode(Redis::lrange($key, 0, -1), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            case 'set':
                return json_encode(Redis::smembers($key), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            case 'zset':
                return json_encode(Redis::zrange($key, 0, -1), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            case 'hash':
                return json_encode(Redis::hgetall($key), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            default:
                return '(unknown type)';
        }
    }

    protected function shortValue($value, $limit = 200)
    {
        if (is_string($value) && strlen($value) > $limit) {
            return substr($value, 0, $limit) . '...';
        }
        return $value;
    }
}
