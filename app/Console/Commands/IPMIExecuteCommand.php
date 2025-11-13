<?php

namespace App\Console\Commands;

use App\Jobs\DataSyncer;
use App\Jobs\PowerCrawler;
use App\Jobs\PowerExecutor;
use App\Jobs\SensorCrawler;
use App\Services\HostService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

class IPMIExecuteCommand extends Command
{

    protected $signature = 'ipmi:execute {mode : on:<ip>|off:<ip>|reset:<ip>}';
    protected $description = 'Dispatch job PowerExecutor để bật/tắt/reset một host cụ thể (không hỗ trợ all)';

    private $processor_execute;
    private $processor_power;
    private $processor_sensor;
    private $processor_update;

    public function __construct()
    {
        parent::__construct();
        $this->processor_execute = config('queue.processor.user_execute');
        $this->processor_power   = config('queue.processor.user_power');
        $this->processor_sensor  = config('queue.processor.user_sensor');
        $this->processor_update  = config('queue.processor.user_update');

    }

    public function handle(HostService $hostService)
    {
        $mode = $this->argument('mode');

        if (!$input = $this->syntax_handler($mode, $hostService)) {
            return Command::FAILURE;
        }

        $this->processor_dispatcher($input['host'], $input['action']);

        $this->info("Đã gửi lệnh thực thi {$input['action']} cho {$input['host']->ip} thành công!");

        return Command::SUCCESS;

    }


    public function syntax_handler($mode, HostService $hostService) {

        $parts = explode(':', $mode);
        if (count($parts) !== 2) {
            $this->error('Sai cú pháp. Dùng: php artisan ipmi:power on:192.168.1.10');
            return null;
        }

        [$action, $ip] = $parts;

        if (!in_array($action, ['on', 'off', 'reset'])) {
            $this->error("Hành động không hợp lệ: $action");
            return null;
        }

        $host = $hostService->ip($ip);

        if (!$host) {
            $this->error("Không tìm thấy host có IP: {$ip}");
            return null;
        }

        return [
            'ip' => $ip,
            'action' => $action,
            'host' => $host
        ];
    }

    /**
     * Phân phối các Job xử lý theo đúng pipeline
     */

    public function processor_dispatcher($host, $action)
    {
        Bus::chain([

            (new PowerExecutor($host->ip, $host->username, $host->password, $action,$this->processor_execute))
                ->onQueue($this->processor_execute),

            (new SensorCrawler($host->ip, $host->username, $host->password, $this->processor_sensor))
                ->onQueue($this->processor_sensor),

            (new PowerCrawler($host->ip, $host->username, $host->password, $this->processor_power))
                ->onQueue($this->processor_power),

            (new DataSyncer($host->ip,$this->processor_update))
                ->onQueue($this->processor_update),

        ])->dispatch();
    }
}
