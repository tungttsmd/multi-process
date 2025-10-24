<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SyncHostToSensorSeeder extends Seeder
{
    public function run(): void
    {
        $hosts = DB::table('hosts')->select('ip')->get();

        foreach ($hosts as $host) {
            // Chèn bản ghi sensor tương ứng
            DB::table('sensors')->updateOrInsert(
                ['ip' => $host->ip],
                [
                    'log' => null,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
