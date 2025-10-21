<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Livewire\Component;

class IpmiSensorFetcher extends Component
{
    protected $listeners = ['sensorFetch' => 'fetch']; // khai báo listener

    public function fetch() {
        $url = url("/api/ipmi/sensor/fetch");

        try {
            $response = Http::get($url)->json();
        } catch (\Throwable $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'result'  => json_encode(''),
            ];
        }

        // // ✅ Emit kết quả về lại client
        // $this->dispatch('sensorFetchResult', [
        //     'status' => 'success',
        //     'message' => 'ok',
        //     'result'  => $response,
        // ]);

        return response()->json([
            'status' => 'success',
            'message' => 'ok',
            'result'  => $response,
        ]);
    }

    // Không cần render view
    public function render()
    {
        return <<<'HTML'
        <div style="display:none"></div>
        HTML;
    }
}
