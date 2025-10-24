<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SyncHostToPowerSeeder extends Seeder
{
    public function run(): void
    {
        $hosts = DB::table('hosts')->select('ip')->get();

        foreach ($hosts as $host) {
            // Chèn bản ghi status tương ứng
            DB::table('powers')->updateOrInsert(
                ['ip' => $host->ip],
                [
                    'log' => null,
                    'updated_at' => now(),
                ]
            );
        }
    }
}
