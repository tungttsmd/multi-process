<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\Process\Process;

class StatusCrawler implements ShouldQueue
{
    use Queueable;

    protected $command;
    protected $channelLogFile;
    protected $redisKey;
    protected $host_ip;
    protected $username;
    protected $password;

    public function __construct($host_ip, $username, $password)
    {
        $this->host_ip  = $host_ip;
        $this->username = $username;
        $this->password = $password;

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

        $this->channelLogFile = 'host_status_log';
        $hostRedisFormat      = str_replace('.', '_', $this->host_ip);
        $this->redisKey       = "ipmi_status:$hostRedisFormat";
    }

    public function handle(): void
    {
        try {
            $p = new Process($this->command);
            $p->setTimeout(1.6);
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

            $json = $this->parseStatusOutput($stdout);
            Log::channel($this->channelLogFile)->info($json);
            Redis::rpush($this->redisKey, $json);
        } catch (\Throwable $e) {
            $error = $this->makeResponse('error', $e->getMessage());
            Log::channel($this->channelLogFile)->error($error);
            Redis::rpush($this->redisKey, $error);
        }
    }

    protected function parseStatusOutput(string $rawOutput): string
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
            throw new \RuntimeException($rawOutput ?: 'Unable to determine power state');
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
            'data'      => empty($data) || $data === '' ? new \stdClass() : $data,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
