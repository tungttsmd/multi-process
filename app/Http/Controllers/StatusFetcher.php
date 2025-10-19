<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusFetcher extends Controller
{
    public function all() {
        $hosts = DB::table('hosts')->select('ip', 'name')->get();
        $results = [];
        foreach ($hosts as $host) {
            try {
                $statusData = $this->get($host->ip);

                if (isset($statusData->data)) {
                    $results[] = [
                        'ip' => $host->ip,
                        'name' => $host->name ?? $host->ip,
                        'power' => $statusData->data->power,
                        'status' => $statusData->status ?? 'unknown',
                        'last_updated' => now()->toDateTimeString()
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'ip' => $host->ip,
                    'name' => $host->name ?? $host->ip,
                    'error' => 'Failed to fetch status data',
                    'status' => 'error'
                ];
                // Log the error for debugging
                \Log::error("Error fetching status data for {$host->ip}: " . $e->getMessage());
            }
        }

        return response()->json([
            'success' => true,
            'data' => $results,
            'timestamp' => now()->toDateTimeString()
        ]);
    }
    public function get($ip)  {
    $data = DB::table('statuses')
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
