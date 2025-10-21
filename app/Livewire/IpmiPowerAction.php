<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Http;
use Livewire\Component;

class IpmiPowerAction extends Component
{
    protected $listeners = ['powerAction' => 'action']; // khai báo listener

    public function action($payload)
    {
        $ip = $payload['ip'];
        $action = $payload['action'];

        // ✅ Gọi API nội bộ Laravel để chạy lệnh IPMI
        $url = url("/api/ipmi/power/{$ip}/{$action}");

        try {
            $response = Http::get($url)->json();
        } catch (\Throwable $e) {
            $response = [
                'status' => 'error',
                'message' => $e->getMessage(),
                'result'  => json_encode(''),
            ];
        }

        // ✅ Emit kết quả về lại client
        $this->dispatch('powerActionResult', [
            'status'      => 'success',
            'message'  => json_encode([
                'action' => $action,
                'ip' => $ip
            ]),
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
