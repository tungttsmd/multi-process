<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class PowerFetcher extends Controller
{
    protected $table;

    function __construct() {
        $this->table = 'statuses';
    }

     /**
     * Main Handler to fetch all statuses
     */
    public function fetchAll() {
        $hosts = DB::table('hosts')
        ->select('ip', 'name')
        ->orderBy('name')
        ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'data' => $this->stepFetchAll($hosts)
        ]);
    }

    /**
     * Fetch all statuses
     * @param $hosts
     */
    public function stepFetchAll($hosts) {
        $results = [];

        foreach ($hosts as $host) {
            try {

                $statusData = $this->fetch($host->ip);

                if (isset($statusData->data)) {
                    $results[] = [
                        'ip' => $host->ip,
                        'power' => $statusData->data->power,
                        'last_updated' => now()->toDateTimeString()
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'ip' => $host->ip,
                    'power' => 'disconnected',
                    'last_updated' => now()->toDateTimeString()
                ];
            }
        }

        return $results;
    }

    /**
     * Fetch status for a single host
     * @param $ip
     */
    public function fetch($ip)  {

        $data = DB::table($this->table)
            ->select('log')
            ->where('ip', $ip)
            ->first();

        if (!$data) {
            return response()->json(['error' => "not found data for $ip"], 404);
        }

        $json = json_decode(trim($data->log));
        return json_decode($json);
    }
}
