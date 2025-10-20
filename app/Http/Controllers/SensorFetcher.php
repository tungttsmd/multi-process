<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SensorFetcher extends Controller
{
    public function all(){
        // Lấy danh sách host và sắp xếp theo tên
        $hosts = DB::table('hosts')
            ->select('ip', 'name')
            ->orderBy('name')
            ->get();
            
        $results = [];

        foreach ($hosts as $host) {
            try {
                $sensorData = $this->get($host->ip);

                if (isset($sensorData->data)) {
                    $results[] = [
                        'ip' => $host->ip,
                        'name' => $host->name ?? $host->ip,
                        'cpu0_temp' => $sensorData->data->CPU0_Temp ?? 'N/A',
                        'cpu1_temp' => $sensorData->data->CPU1_Temp ?? 'N/A',
                        'cpu0_fan' => $sensorData->data->CPU0_FAN ?? 'N/A',
                        'cpu1_fan' => $sensorData->data->CPU1_FAN ?? 'N/A',
                        'status' => $sensorData->status ?? 'unknown',
                        'last_updated' => now()->toDateTimeString()
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'ip' => $host->ip,
                    'name' => $host->name ?? $host->ip,
                    'error' => 'Failed to fetch sensor data',
                    'status' => 'error'
                ];
                // Log the error for debugging
                \Log::error("Error fetching sensor data for {$host->ip}: " . $e->getMessage());
            }
        }

        // Sắp xếp kết quả theo tên (trường hợp có thêm logic xử lý sau này)
        usort($results, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return response()->json([
            'success' => true,
            'data' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

   public function get($ip) {
    $data = DB::table('sensors')
        ->select('log')
        ->where('ip', $ip)
        ->first();

    if (!$data) {
        return (object)[
            'status' => 'error',
            'message' => "Không tìm thấy log cho $ip",
        ];
    }

    try {
        // Dữ liệu trong cột 'log' có thể là JSON gốc hoặc JSON bọc trong chuỗi
        $log = trim($data->log);

        // Nếu nó là chuỗi JSON lồng, decode 1 lần
        $decoded = json_decode($log, false);

        // Nếu JSON bị bọc 2 lớp (ví dụ: "\"{...}\""), decode thêm 1 lần
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, false);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(json_last_error_msg());
        }

        return $decoded;
    } catch (\Exception $e) {
        \Log::error("JSON parse error for {$ip}: " . $e->getMessage());
        return (object)[
            'status' => 'error',
            'message' => 'Invalid JSON data',
        ];
    }
}
}

