<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class HostSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $host_name_list = ['101' => '127.0.0.1', '102' => '127.0.0.2', '103' => '127.0.0.3', '104' => '127.0.0.4', '105' => '127.0.0.5', '106' => '127.0.0.6', '107' => '127.0.0.7', '108' => '127.0.0.8', '109' => '127.0.0.9', '110' => '127.0.0.10'];
        foreach ($host_name_list as $host_name => $host_ip) {
            DB::table('hosts')->insert([
                'name' => $host_name,
                'ip' => $host_ip,
                'username' => 'admin',
                'password' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
