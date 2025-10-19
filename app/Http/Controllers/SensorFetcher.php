<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SensorFetcher extends Controller
{
    public function all(){
        $hosts = DB::table('hosts')->select('ip', 'name')->get();
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

        return response()->json([
            'success' => true,
            'data' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    public function get($ip)  {
    $data = DB::table('sensors')
        ->select('log')
        ->where('ip', $ip)
        ->first();
    if (!$data) {
        return response()->json(['error' => "Không tìm thấy log cho $ip"], 404);
    }

    $json = json_decode(trim($data->log));
    $return = json_decode($json);
    return $return;

    }
}
