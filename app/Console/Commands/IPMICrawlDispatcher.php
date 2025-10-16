<?php

namespace App\Console\Commands;

use App\Jobs\SensorCrawler;
use App\Jobs\StatusCrawler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IPMICrawlDispatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $timeout = 2;
    protected $signature = 'ipmi:get
    {--h= : Tên host cần thao tác}
    {--a= : Action cần thực hiện (status/sensor)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatcher crawlers (sensor or status)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->option('a');
        $host = $this->option('h');

        if (in_array($action, ['sensor', 'status'])) {
            if ($host !== 'all') {
                $list = DB::table('hosts')->where('ip', $host)->first();
                try {
                    if ($action === 'sensor') {
                        $this->sensor($list->ip, $list->username, $list->password);
                        $this->success("Đã dispatch sensor crawler cho host: {$list->ip}");
                    } else {
                        $this->state($list->ip, $list->username, $list->password);
                        $this->success("Đã dispatch status crawler cho host: {$list->ip}");
                    }
                } catch (\Exception $e) {
                    $this->error("Gặp lỗi khi dispatch status & sensor crawler cho host: {$list->ip}");
                }
            } else {
                $list = DB::table('hosts')->get();
                $index = 0;
                foreach ($list as $item) {
                    try {
                        // Tính toán để chia queue đều các queue processor
                        $queueList = $this->ditributeQueues();
                        $processorSensors = $queueList['sensor'];
                        $processorStatuses = $queueList['status'];
                        $queueSensor = $processorSensors[$index % count($processorSensors)];
                        $queueStatus = $processorStatuses[$index % count($processorStatuses)];
                        $this->line(print_r($processorSensors, true));
                        $this->line(print_r($processorStatuses, true));

                        if ($action === 'sensor') {
                            $this->sensor($item->ip, $item->username, $item->password, $queueSensor);
                            $this->success("$queueSensor - Đã dispatch sensor crawler cho host: {$item->ip}");
                        } else {
                            $this->state($item->ip, $item->username, $item->password,  $queueStatus);
                            $this->success("$queueStatus - Đã dispatch status crawler cho host: {$item->ip}");
                        }

                        $index++;
                    } catch (\Exception $e) {
                        $this->error("Gặp lỗi khi dispatch status & sensor crawler cho host: {$item->ip}");
                    }
                }
                $this->line("Tổng số lượng đã dispatch: " . count($list));
            }
        } else {
            $this->error('Action không hợp lệ (status/sensor)');
        }
    }
    protected function sensor(string $ip, string $username, string $password, string $queue = 'default')
    {
        dispatch(new SensorCrawler($ip, $username, $password))->onQueue($queue);
    }
    protected function state(string $ip, string $username, string $password, string $queue = 'default')
    {
        dispatch(new StatusCrawler($ip, $username, $password))->onQueue($queue);;
    }

    protected function ditributeQueues()
    {
        // Lấy cấu hình queue từ config (env fallback)
        $sensorQueues = config('queue.processor.sensor', ['default']);
        $statusQueues = config('queue.processor.status', ['default']);

        if (!is_array($sensorQueues)) {
            $sensorQueues = array_filter(array_map('trim', explode(',', $sensorQueues)));
            if (empty($sensorQueues)) $sensorQueues = ['default'];
        }
        if (!is_array($statusQueues)) {
            $statusQueues = array_filter(array_map('trim', explode(',', $statusQueues)));
            if (empty($statusQueues)) $statusQueues = ['default'];
        }
        return [
            'status' => $statusQueues,
            'sensor' => $sensorQueues
        ];
    }

    protected function success(string $message)
    {
        $this->line("<fg=green>{$message}</>");
    }
    protected function clean()
    {
        $this->output->write("\033c"); // reset screen (ANSI)
    }
    protected function pause()
    {
        fgets(STDIN);
    }
}
