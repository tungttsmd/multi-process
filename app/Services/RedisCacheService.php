<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class RedisCacheService
{
    private string $queue_worker;
    private string $key_name;
    private string $key;

    /**
     * Service ghi/đọc/tăng/giảm cache redis
     */
    public function __construct(string $key_name, string $queue_worker = "default")
    {
        $this->queue_worker = $queue_worker;
        $this->key_name = $key_name;
        $this->key = "{$this->queue_worker}:{$this->key_name}";
    }

    /** Trả về thông tin key hiện tại */
    public function info(): array
    {
        return [
            'key' => $this->key,
            'queue_worker' => $this->queue_worker,
            'key_name' => $this->key_name
        ];
    }

    /** Tăng giá trị số */
    public function inc(): void
    {
        Cache::increment($this->key);
    }

    /** Giảm giá trị số */
    public function dec(): void
    {
        Cache::decrement($this->key);
    }


    /** Lây key*/
    public function key()
    {
        return $this->key;
    }

    /** Đọc giá trị cache */
    public function get()
    {

        return Cache::get($this->key, "empty");
    }

    /** Ghi giá trị cache */
    public function set(string $value, int $minutes = 12): void
    {
        Cache::put($this->key, $value, now()->addMinutes($minutes));
    }

    static function view($key)
    {
        return Cache::get($key, null);
    }

     /** Convert host đúng chuẩn */
    static function formatHost(string $ip) {
        return str_replace('.', '_', $ip);
    }

    /** Hàm tĩnh xóa cache bất kì */
    static function remove($key)
    {
        $prefix = config('cache.prefix');
        if (!str_starts_with($key, $prefix)) {
            $key = "{$prefix}{$key}";
        }

        Redis::del($key);
    }


}
