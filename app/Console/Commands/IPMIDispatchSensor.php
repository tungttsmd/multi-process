<?php

namespace App\Console\Commands;

use App\Jobs\SensorCrawler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class IPMIDispatchSensor extends Command
{
    protected $signature = 'ipmi:sensor {mode : all|host:<ip>}';
    protected $description = 'Dispatch job SensorCrawler cho toàn bộ host hoặc 1 host cụ thể';

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
                $lock_key = "ipmi_sensor_dispatch_lock:ip:$host->ip";
                $lock_cache = Cache::lock($lock_key, 30); // Cho phép job treo 360 giây

                if ($lock_token = $lock_cache->get()) {
                // Hoạt động $lock_cache->get() sẽ thực sự đưa vào cache Redis một key dùng để lock việc chạy job trùng
                // $lock_cache->get() trả về token, dùng token đưa vào job SensorCralwer
                // Có token, job có thể tái tạo và trỏ đúng key trong redis để xóa chính xác key này
                // Sau khi xóa là job đã xong (theo logic lập trình), thì ở đây mới cho dispatch tiếp
                // Đảm bảo tính unique của job

                    dispatch(new SensorCrawler($host->ip, $host->username, $host->password, $lock_token))
                    ->onQueue('processor_sensor_' . (($index % 28) + 1));
                    $this->success("Đã dispatch SensorCrawler cho {$host->ip}");
                    Log::channel('host_sensor_log')
                        ->info('Dispatch mới: '. $host->ip);

                        // Code nhả key $lock_cache->release() không đặt ở đây nhé mà đặt trong handle() của SensorCralwer chạy job

                } else {
                    Log::channel('host_sensor_log')
                        ->info('Job chưa thực hiện xong, bỏ qua dispatch lần này: '. $host->ip);
                    continue;
                }
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

            $processor = config('queue.processor.user_sensor');
            $this->info(json_encode($processor));

            $lock_key = "user_ipmi_sensor_dispatch_lock:ip:$host->ip";
            $lock_cache = Cache::lock($lock_key, 360); // Cho phép job treo 360 giây


            if ($lock_user_token = $lock_cache->get()) {
                dispatch(new SensorCrawler($host->ip, $host->username, $host->password, $lock_user_token))
                    ->onQueue($processor);


                $this->info("Đã dispatch SensorCrawler cho {$host->ip}");
                return;
            }

            $this->info("Thất bại dispatch SensorCrawler cho {$host->ip} do job đã tồn tại");
            return;
        }

        $this->error('Sai cú pháp. Dùng: php artisan ipmi:sensor all hoặc php artisan ipmi:sensor host:192.168.1.10');
    }

    protected function success(string $message)
    {
        $this->line("<fg=green>{$message}</>");
    }
}
