<?php

namespace App\Console\Commands;

use App\Jobs\SensorCrawler;
use App\Jobs\StateCrawler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IPMICrawlDispatcher extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:get
    {--h= : Tên host cần thao tác}
    {--a= : Action cần thực hiện (state/sensor)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatcher crawlers (sensor or state)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->option('a');
        $host = $this->option('h');

        if (in_array($action, ['sensor', 'state'])) {
            if ($host !== 'all') {
                $list = DB::table('hosts')->where('ip', $host)->first();
                try {
                    if ($action === 'sensor') {
                        $this->sensor($list->ip, $list->username, $list->password);
                        $this->success("Đã dispatch sensor crawler cho host: {$list->ip}");
                    } else {
                        $this->state($list->ip, $list->username, $list->password);
                        $this->success("Đã dispatch state crawler cho host: {$list->ip}");
                    }
                } catch (\Exception $e) {
                    $this->error("Gặp lỗi khi dispatch state & sensor crawler cho host: {$list->ip}");
                }
            } else {
                $list = DB::table('hosts')->get();
                foreach ($list as $item) {
                    try {
                        if ($action === 'sensor') {
                            $this->sensor($item->ip, $item->username, $item->password);
                            $this->success("Đã dispatch sensor crawler cho host: {$item->ip}");
                        } else {
                            $this->state($item->ip, $item->username, $item->password);
                            $this->success("Đã dispatch state crawler cho host: {$item->ip}");
                        }
                    } catch (\Exception $e) {
                        $this->error("Gặp lỗi khi dispatch state & sensor crawler cho host: {$item->ip}");
                    }
                }
            }
        } else {
            $this->error('Action không hợp lệ (state/sensor)');
        }
    }
    protected function sensor(string $ip, string $username, string $password)
    {
        dispatch(new SensorCrawler($ip, $username, $password));
    }
    protected function state(string $ip, string $username, string $password)
    {
        dispatch(new StateCrawler($ip, $username, $password));
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
