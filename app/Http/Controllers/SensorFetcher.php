<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class SensorFetcher extends Controller
{

    protected $table;

    function __construct() {
        $this->table = 'sensors';
    }

    public function fetchAll(){
        $hosts = DB::table('hosts')
            ->select('ip', 'name')
            ->orderBy('name')
            ->get();

        $results = $this->stepFetchAll($hosts);

        return response()->json([
            'status' => 'success',
            'data' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Fetch sensor data for a single host
     * @param $ip
     */
    public function fetch($ip) {
        $data = DB::table($this->table)
            ->select('log')
            ->where('ip', $ip)
            ->first();

        if (!$data) {
            return response()->json([
                'status' => 'error',
                'message' => "Không tìm thấy dữ liệu cho $ip",
                'data' => [],
            ], 404);
        }

        return $this->decode($data->log);
    }

    /**
     * Fetch all statuses
     * @param $hosts
     */
    public function stepFetchAll($hosts) {
        $results = [];
        foreach ($hosts as $host) {
            try {
                $sensorData = $this->fetch($host->ip);

                if (isset($sensorData->data)) {
                    $results[] = [
                        'ip' => $host->ip,
                        'status' => 'success',
                        'message' => 'ok',
                        'cpu0_temp' => $sensorData->data->CPU0_Temp ?? 'N/A',
                        'cpu1_temp' => $sensorData->data->CPU1_Temp ?? 'N/A',
                        'cpu0_fan' => $sensorData->data->CPU0_FAN ?? 'N/A',
                        'cpu1_fan' => $sensorData->data->CPU1_FAN ?? 'N/A',
                        'last_updated' => now()->toDateTimeString()
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'ip' => $host->ip,
                    'status' => 'error',
                    'message' => 'Something went wrong',
                    'cpu0_temp' => 'N/A',
                    'cpu1_temp' => 'N/A',
                    'cpu0_fan' => 'N/A',
                    'cpu1_fan' => 'N/A',
                    'last_updated' => now()->toDateTimeString(),

                ];
            }
        }

       return $results;
    }

    /**
     * Decode JSON data
     * @param $json
     */
    protected function decode($json) {
        try {
            // Dữ liệu trong cột 'log' có thể là JSON gốc hoặc JSON bọc trong chuỗi
            $log = trim($json);

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
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid JSON data: '.$e->getMessage(),
                'data' => [],
            ], 500);
        }
    }
}

