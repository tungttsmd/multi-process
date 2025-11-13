<?php

namespace App\Console\Commands;

use App\Jobs\SensorCrawler;
use App\Services\HostService;
use App\Services\RedisDispatchCommandService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class IPMISensorCommand extends Command
{
    protected $signature = 'ipmi:sensor {mode : all|host:<ip>}';
    protected $description = 'Dispatch job SensorCrawler cho toàn bộ host hoặc 1 host cụ thể';

    private $prefixProcessor;
    private $processors;
    private $user_processor;

    public function __construct() {

        parent::__construct();
        $this->prefixProcessor = 'processor_sensor_';
        $this->processors = config('queue.processor.sensor');
        $this->user_processor = config('queue.processor.user_sensor');

    }

    public function handle(HostService $hostService)
    {
        $mode = $this->argument('mode');

        // Bước 1: Kiểm tra cú pháp hợp lệ
        if (!$this->validSyntax($mode, $hostService)) {
            return Command::INVALID;
        }

        // Bước 2: Nếu là all
        if ($mode === 'all') {
            $this->handlerAllSyntax($hostService);
            return Command::SUCCESS;
        }

        // Bước 3: Nếu là host:<ip>
        if (str_starts_with($mode, 'host:')) {
            $this->handlerIpSyntax($mode, $hostService);
            return Command::SUCCESS;
        }

    }

    protected function validSyntax(string $mode, HostService $hostService): bool
    {
        if ($mode === 'all') {
            $hosts = $hostService->all();

            if ($hosts->isEmpty()) {
                $this->warn('Không có host nào trong bảng hosts.');
                return false;
            }
            return true;
        }

        if (str_starts_with($mode, 'host:')) {
            $ip = substr($mode, 5);
            $host = $hostService->ip($ip);

            if (!$host) {
                $this->error("Không tìm thấy host có IP: {$ip}");
                return false;
            }

            return true;
        }

        $this->error('Sai cú pháp. Dùng: php artisan ipmi:sensor all hoặc php artisan ipmi:sensor host:<ip>');
        return false;
    }

    protected function handlerAllSyntax(HostService $hostService) {

        $hosts = $hostService->all();

        foreach ($hosts as $index => $host) {

            if (RedisDispatchCommandService::isExist($host->ip, $this->queue($index))) {
                $this->info("[Tiến trình: ".$this->queue($index)."] Bỏ qua vì đã tồn tại trong Redis cache Job lấy sensor của host {$host->ip} ");
                continue;
            }

            dispatch(new SensorCrawler($host->ip, $host->username, $host->password, $this->queue($index)))
            ->onQueue($this->queue($index));

            RedisDispatchCommandService::create(
                $host->ip,
                "processor_sensor",
                $this->redisContent($this->queue($index), $host->ip)
            );

            $this->info("[Tiến trình: ".$this->queue($index)."] Đã dispatch Job SensorCrawler cho {$host->ip}");
        }

        $this->info("Đã dispatch tất cả (" . count($hosts) . " hosts).");

        return;
    }

    protected function handlerIpSyntax($mode, HostService $hostService) {
        $ip = substr($mode, 5);

        $host = $hostService->ip($ip);

        if (!$host) {
            $this->error("Không tìm thấy host có IP: {$ip}");
            return;
        }

        dispatch(new SensorCrawler($host->ip, $host->username, $host->password, $this->user_processor))
            ->onQueue($this->user_processor);

        RedisDispatchCommandService::create(
            $host->ip,
            $this->user_processor,
            $this->redisContent($this->user_processor, $host->ip)
        );

        $this->info("[Tiến trình: ".$this->user_processor."] Đã dispatch SensorCrawler cho {$host->ip}");
        return;
    }

    protected function queue($index) {
        $processorCount = count($this->processors);
        return $this->prefixProcessor . (($index % $processorCount) + 1);
    }

    protected function redisContent($queue, $ip) {
        return [
            'host_ip'=> $ip,
            'queue_worker'=> $queue,
            'dispatch'=> 'Jobs/SensorCrawler.php',
            'note' => 'Job này lấy dữ liệu nhiệt độ, tốc độ quạt của cpu 1 và 2 trên các host có ipmi',
            'timestamp'=> Carbon::now()->format('d-m-Y H:i:s'),
        ];
    }

}

