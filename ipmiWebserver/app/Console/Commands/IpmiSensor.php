<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class IpmiSensor extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ipmi:sensor {--limit=50}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Chạy IPMI sensor từ danh sách host và lưu vào DB';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $limit = (int) $this->option('limit');
        $host = DB::table('hosts')->limit($limit)->get();
        if ($host->isEmpty()) {
            $this->error('Không có host nào để quét sensor');
            return;
        } else {
            foreach ($host as $item) {
                $this->info("Quét sensor cho host: " . $item->name);
                dispatch(new IpmiSensorJob($item));
            }
        }
    }
}
