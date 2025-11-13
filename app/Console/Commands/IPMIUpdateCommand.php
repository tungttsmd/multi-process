<?php

namespace App\Console\Commands;

use App\Jobs\DataSyncer;
use App\Services\HostService;
use App\Services\RedisDispatchCommandService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class IPMIUpdateCommand extends Command
{

    protected $signature = 'ipmi:update {mode : all|host:<ip>}';
    protected $description = 'Lệnh đẩy Redis vào Database';
    protected $prefixProcessor;
    protected $processors;
    protected $user_processor;

    public function __construct()
    {
        parent::__construct();
        $this->prefixProcessor = 'processor_update_';
        $this->processors = config('queue.processor.update');
        $this->user_processor = config('queue.processor.user_update');

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

            if (RedisDispatchCommandService::isExist($host->ip, "processor_update")) {
                $this->info("[Tiến trình: ".$this->queue($index)."] Bỏ qua vì đã tồn tại trong Redis cache Job update đẩy data từ Redis vào Database của host {$host->ip} ");
                continue;
            }

            dispatch(new DataSyncer($host->ip, $this->queue($index)))
            ->onQueue($this->queue($index));

            RedisDispatchCommandService::create(
                $host->ip,
                "processor_update",
                $this->redisContent($this->queue($index), $host->ip)
            );

            $this->info("[Tiến trình: ".$this->queue($index)."] Đã dispatch DataSyncer cho {$host->ip}");
            sleep(0.2);
        }
    }

    protected function handlerIpSyntax($mode, HostService $hostService) {

        $ip = substr($mode, 5);
        $host =$hostService->ip($ip);

        if (!$host) {
            $this->error("Không tìm thấy host có IP: {$ip}");
            return;
        }

        dispatch(new DataSyncer($ip,  $this->user_processor))
        ->onQueue( $this->user_processor);

        RedisDispatchCommandService::create(
                $ip,
                $this->user_processor,
                $this->redisContent($this->user_processor, $ip)
        );

        $this->info("[Tiến trình: ".$this->user_processor."] Đã dispatch DataSyncer cho {$ip}");
        return;
    }

    protected function queue($index) {
        $processorCount = count($this->processors);
        return $this->prefixProcessor . (($index % $processorCount) + 1);
    }

     protected function redisContent($queue, $ip) {
        return [
            'host_ip'=> $ip,
            'queue_worker'=>$queue,
            'dispatch'=> 'Jobs/DataSyncer.php',
            'note' => 'Job này đẩy dữ liệu redis vào database',
            'timestamp'=> Carbon::now()->format('d-m-Y H:i:s')
        ];
    }
}
