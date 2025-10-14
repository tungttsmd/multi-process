<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Symfony\Component\Process\Process;
use App\Console\Commands\SystemTest;
use Illuminate\Support\Facades\Log;

class ipmiSensorJob implements ShouldQueue
{
    use Queueable;
    public $tries = 1; // Biến chỉ định JOB TRY

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $hosts = ['google.com', 'roblox.com', 'facebook.com', 'youtube.com', 'github.com', 'twitter.com'];
        $processes = [];
        foreach ($hosts as $host) {
            $p = new Process(['ping', $host, '-n', '3']);
            $p->setTimeout(3);
            $p->start();
            $processes[$host] = $p;
            Log::info('Đang bắt đầu ping: ' . $host);
        };

        throw new \Exception("Lỗi job");
        $this->unsynchronizedOutput($processes);
    }
    protected function unsynchronizedOutput($processes)
    {
        try {
            $done = [];
            do {
                $running = false;
                foreach ($processes as $host => $p) {
                    if ($p->isRunning()) {
                        $running = true;
                        continue;
                    }

                    if (!isset($done[$host])) {
                        $output = trim($p->getOutput());
                        Log::info("Hoàn tất: {$host}\n{$output}");
                        $done[$host] = true;
                    }
                }
                usleep(500000);
            } while ($running);
            Log::info('Hoàn tất toàn bộ job');
        } catch (\Exception $e) {
            Log::error('Lỗi trong job: ' . $e->getMessage());
            $this->fail("Lỗi job: " . $e->getMessage());
        }
    }
}
