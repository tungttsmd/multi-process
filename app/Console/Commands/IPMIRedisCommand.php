<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class IPMIRedisCommand extends Command
{
    protected $signature = 'redis:explore {pattern? : Mẫu key ban đầu, ví dụ ipmi_*}';
    protected $description = 'Trình duyệt Redis tương tác trong terminal.';

    public function handle(): void
    {
        $pattern = $this->argument('pattern') ?? '*';
        $this->explore($pattern);
    }

    private function explore(string $pattern): void
    {
        $keys = Redis::keys($pattern);
        if (empty($keys)) {
            $this->warn("⚠️ Không tìm thấy key nào khớp với pattern: {$pattern}");
            return;
        }

        sort($keys);
        $total = count($keys);
        $this->info("\n🔍 Tìm thấy {$total} keys với pattern [{$pattern}]");
        $this->line(str_repeat('─', 60));

        // Hiển thị danh sách key có số thứ tự
        foreach ($keys as $i => $key) {
            $ttl = Redis::ttl($key);
            $ttlText = $ttl > 0 ? "{$ttl}s" : ($ttl === -1 ? '∞' : 'expired');
            $this->line("<fg=cyan>[$i]</> <fg=green>{$key}</> <fg=gray>(TTL: {$ttlText})</>");
        }
        $this->line(str_repeat('─', 60));

        // Lặp tương tác
        while (true) {
            $choice = $this->ask("\nNhập số thứ tự để xem chi tiết, hoặc gõ 's' để search, 'q' để thoát");
            if ($choice === 'q') {
                $this->info("👋 Thoát Redis Explorer.");
                return;
            }

            if ($choice === 's') {
                $newPattern = $this->ask("Nhập pattern mới (ví dụ: ipmi_sensor:*)");
                $this->explore($newPattern);
                return;
            }

            if (!is_numeric($choice) || !isset($keys[(int)$choice])) {
                $this->error("❌ Lựa chọn không hợp lệ.");
                continue;
            }

            $key = $keys[(int)$choice];
            $this->showKeyDetails($key);
        }
    }

    private function showKeyDetails(string $key): void
    {
        $ttl = Redis::ttl($key);
        $ttlText = $ttl > 0 ? "{$ttl}s" : ($ttl === -1 ? '∞' : 'expired');
        $type = Redis::type($key);

        $this->line("\n<fg=yellow>Key:</> {$key}");
        $this->line("<fg=gray>Loại:</> {$type}, <fg=gray>TTL:</> {$ttlText}");

        $value = null;
        switch ($type) {
            case 'string':
                $value = Redis::get($key);
                break;
            case 'hash':
                $value = json_encode(Redis::hgetall($key), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'list':
                $value = json_encode(Redis::lrange($key, 0, -1), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'set':
                $value = json_encode(Redis::smembers($key), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
            case 'zset':
                $value = json_encode(Redis::zrange($key, 0, -1, 'WITHSCORES'), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                break;
        }

        if (Str::isJson($value)) {
            $value = json_encode(json_decode($value), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }

        $this->line("<fg=gray>Value:</>\n" . $this->colorizeJson($value));
        $this->line(str_repeat('─', 60));
    }

    private function colorizeJson(?string $json): string
    {
        if (!$json) return "<fg=red>null</>";
        $json = preg_replace('/"([^"]+)":/', '<fg=cyan>"$1"</>:', $json);
        $json = preg_replace('/: "([^"]+)"/', ': <fg=green>"$1"</>', $json);
        $json = preg_replace('/: (\d+)/', ': <fg=yellow>$1</>', $json);
        return $json;
    }
}
