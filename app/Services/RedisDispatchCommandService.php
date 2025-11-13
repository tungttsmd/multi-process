<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;

class RedisDispatchCommandService extends Controller
{
    /**
     * Dữ liệu set này chỉ xem ở redis cli
     * runtime_queue là processor thực sự chạy job trong php artisan queue:work --queue=<runtime_queue>
     * key_processosr là tiền tố của key redis sẽ ghi vào redis cache
     */
    static function create($ip, $redis_key_processor_name, array $content) {

        $ip = RedisCacheService::formatHost($ip);
        (new RedisCacheService("dispatch:".$ip, $redis_key_processor_name))
            ->set(json_encode($content, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }


    /**
    * key_processosr là tiền tố của key redis, bắt đàu của key redis
    *
    */
    static function isExist($host_ip, $redis_key_processor_name) {

            $key = $redis_key_processor_name.":dispatch:".RedisCacheService::formatHost($host_ip);
            $redis_view = RedisCacheService::view($key);

            if ($redis_view !== null) {
                return true;
            }

            return false;
    }
}
