<?php

namespace App\Jobs;

use App\Services\RedisCacheService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Symfony\Component\Process\Process;

class PowerExecutor implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $command;
    protected $channelLogFile;
    protected $queue_worker;
    protected $action;
    protected $host_ip;
    protected $username;
    protected $password;
    protected $redis_host;
    protected $redis_dispatch_note;
    protected $redis_job_done_count;
    protected $redis_job_done;
    protected $redis_job_data;

    /**
     * Create a new job instance.
     */
    public function __construct($host_ip, $username, $password, $action, $queue_worker) {

        $this->username = $username;
        $this->password = $password;

        $this->host_ip  = $host_ip;

        $this->queue_worker = $queue_worker;
         /**
         * redis key:
         *
         * [
         *  execute_job_done_count_host::192_153_4_20 => int 1,
         *  execute_job_done_host:192_153_4_20 => true/false
         *  execute_data_host:192_153_4_20 => json...
         * ]
         */

        // Format host: 192_153_4_20;
        $this->redis_host = str_replace('.', '_', $this->host_ip);

        // Format key: execute_job_done_count_host::192_153_4_20
        $this->redis_job_done_count = new RedisCacheService('execute_job_done_count_host:'.$this->redis_host, $queue_worker);

        // Format key: execute_job_done_host:192_153_4_20
        $this->redis_job_done = new RedisCacheService('execute_job_done_host:'.$this->redis_host, $queue_worker);

        // Format key: execute_data_host:192_153_4_20
        $this->redis_job_data = new RedisCacheService('execute_data_host:'.$this->redis_host, $queue_worker);

        // Redis dispatch note: dispatch:192_153_4_20
        $this->redis_dispatch_note = new RedisCacheService('dispatch:'.$this->redis_host, $queue_worker);

        if (in_array($action, ['on', 'off', 'reset', 'rs'])) {

            if ($action == 'rs') {
                $this->action = 'reset';
            };

            $this->command = [
                'ipmitool', // Execute file của IPMI
                '-I', // Interface (lan/lanplus,usb...)
                'lanplus',
                '-H', // Host
                $this->host_ip,
                '-U', // User
                $this->username,
                '-P', // Password
                $this->password,
                'chassis',
                'power',
                $this->action, // Lệnh thực thi
            ];
        }
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Chạy sensor command
            $p = new Process($this->command);
            $p->run();

            // Đưa dữ liệu vào redis
            $this->doneCaching();

        } catch (\Exception $e) {
        }
    }
    protected function doneCaching() {

        // Xóa dispatch note
        RedisCacheService::remove($this->redis_dispatch_note->key());
        // Đếm count job theo host
        $this->redis_job_done_count->inc();
        // Set true cho job done theo host
        $this->redis_job_done->set(true);
        // Set dữ liệu vào redis theo host
        $this->redis_job_data->set($this->redis_host);
    }
}
