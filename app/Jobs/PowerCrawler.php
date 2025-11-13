<?php

namespace App\Jobs;

use App\Services\RedisCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class PowerCrawler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $command;

    protected $host_ip;

    protected $username;
    protected $password;

    protected $queue_worker;

    protected $redis_dispatch_note;
    protected $redis_job_done_count;
    protected $redis_job_done;
    protected $redis_job_data;
    protected $redis_host;

    public function __construct($host_ip, $username, $password, $queue_worker)
    {
        $this->username = $username;
        $this->password = $password;

        $this->host_ip  = $host_ip;

        $this->queue_worker = $queue_worker;

        /**
         * redis key:
         *
         * [
         *  power_job_done_count_host::192_153_4_20 => int 1,
         *  power_job_done_host:192_153_4_20 => true/false
         *  power_data_host:192_153_4_20 => json...
         * ]
         */

        // Format host: 192_153_4_20;
        $this->redis_host = str_replace('.', '_', $this->host_ip);

        // Format key: power_job_done_count_host::192_153_4_20
        $this->redis_job_done_count = new RedisCacheService('power_job_done_count_host:'.$this->redis_host, $queue_worker);

        // Format key: power_job_done_host:192_153_4_20
        $this->redis_job_done = new RedisCacheService('power_job_done_host:'.$this->redis_host, $queue_worker);

        // Format key: power_data_host:192_153_4_20
        $this->redis_job_data = new RedisCacheService('power_data_host:'.$this->redis_host, $queue_worker);

        // Redis dispatch note: dispatch:192_153_4_20
        $this->redis_dispatch_note = new RedisCacheService('dispatch:'.$this->redis_host, $queue_worker);

        $this->command = [
            'ipmitool',
            '-I',
            'lanplus',
            '-H',
            $this->host_ip,
            '-U',
            $this->username,
            '-P',
            $this->password,
            'chassis',
            'power',
            'status',
        ];
    }

    public function handle(): void
    {
        try {
            $p = new Process($this->command);
            $p->setTimeout(10);
            $p->run();

            $stderr = trim($p->getErrorOutput());
            $stdout = trim($p->getOutput());

            // Timeout thật hoặc thông báo timeout trong error
            if (str_contains($stderr, 'exceeded the timeout') || $p->getExitCode() === 143) {
                throw new \RuntimeException("Timeout after {$p->getTimeout()}s (no IPMI response)");
            }

            if (!$p->isSuccessful()) {
                $err = $stderr ?: 'Unknown process error';
                throw new \RuntimeException($err);
            }

            if ($stdout === '') {
                throw new \RuntimeException('Empty response from IPMI');
            }

        // Đưa dữ liệu vào redis
        $this->doneCaching($this->parseOutput($stdout));

        } catch (\Throwable $e) {
        }
    }

    protected function parseOutput(string $rawOutput): string
    {
        $output = strtolower(trim($rawOutput));
        $power = 'unknown';

        if (str_contains($output, 'chassis power is on')) {
            $power = 'on';
        } elseif (str_contains($output, 'chassis power is off')) {
            $power = 'off';
        } elseif (str_contains($output, 'reset') || str_contains($output, 'cycle')) {
            $power = 'reset';
        }

        if ($power === 'unknown') {
            throw new \RuntimeException($rawOutput ?: 'Power không xác định');
        }

        return $this->makeResponse('success', 'Power status fetched successfully', ['power' => $power]);
    }

    protected function makeResponse(string $status, string $message, array|string $data = ''): string
    {
        return json_encode([
            'ip'        => $this->host_ip,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'status'    => $status,
            'message'   => $message,
            'data'      => empty($data) || $data === '' ? (object) [] : (object) $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    protected function doneCaching($data) {

        // Xóa dispatch note
        RedisCacheService::remove($this->redis_dispatch_note->key());
        // Đếm count job theo host
        $this->redis_job_done_count->inc();
        // Set true cho job done theo host
        $this->redis_job_done->set(true);
        // Set dữ liệu vào redis theo host
        $this->redis_job_data->set($data);
    }
}
