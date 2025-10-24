<?php

namespace App\Services;

use App\Http\Controllers\SensorFetcher;
use App\Http\Controllers\PowerFetcher;
use Illuminate\Support\Facades\Log;

class ServerDataService
{
    public function getCombinedData()
    {
        try {
            $sensorFetcher = new SensorFetcher();
            $powerFetcher = new PowerFetcher();

            // Get sensor data
            $sensorsResponse = $sensorFetcher->fetchAll();
            $sensors = json_decode($sensorsResponse->getContent(), true);

            // Get power status data
            $powersResponse = $powerFetcher->fetchAll();
            $powers = json_decode($powersResponse->getContent(), true);

            $combined = [];

            // Process sensors data
            if (isset($sensors['data']) && is_array($sensors['data'])) {
                foreach ($sensors['data'] as $sensor) {
                    if (!isset($sensor['ip'])) continue;

                    // Find matching power data
                    $powerData = [];
                    if (isset($powers['data']) && is_array($powers['data'])) {
                        foreach ($powers['data'] as $power) {
                            if (($power['ip'] ?? '') === $sensor['ip']) {
                                $powerData = $power;
                                break;
                            }
                        }
                    }

                    $combined[] = array_merge($sensor, [
                        'status' => $powerData['status'] ?? 'offline',
                        'power' => $powerData['power'] ?? 'off',
                        'name' => $sensor['name'] ?? 'Unknown',
                        'ip' => $sensor['ip']
                    ]);
                }
            }

            return $combined;

        } catch (\Exception $e) {
            return [];
        }
    }
}
