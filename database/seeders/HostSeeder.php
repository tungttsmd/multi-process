<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Host;
use Illuminate\Support\Facades\DB;

class HostSeeder extends Seeder
{
    public function run(): void
    {
        // JSON của bạn — có thể lấy từ file, hoặc copy trực tiếp vào đây
        $json = file_get_contents(storage_path('app/hosts.json'));

        $hosts = json_decode($json, true);

       foreach ($hosts as $h) {
        Host::updateOrCreate([
            'ip' => $h['ip'],
            'name' => $h['name'],
            'username' => 'admin',
            'password' => 'admin',
            'created_at' => now(),
            'updated_at' => now()
            ]);
        }
    }
}
