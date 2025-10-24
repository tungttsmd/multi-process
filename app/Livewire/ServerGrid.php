<?php

namespace App\Livewire;

use Livewire\Component;
use App\Services\ServerDataService;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Facades\Http;

class ServerGrid extends Component
{
    public $search = '';
    public $servers = [];
    public $openPowerOptionsIp = null;

    public function mount()
    {
        $this->loadData();
    }

    public function loadData()
    {

        try {
            $service = new ServerDataService();
            $data = $service->getCombinedData();

            if (!empty($data) && is_array($data)) {
                $this->servers = $data;

                if (empty($data)) {
                    $this->dispatch('notify', [
                        'type' => 'info',
                        'message' => 'Không tìm thấy dữ liệu server. Vui lòng kiểm tra kết nối cơ sở dữ liệu.'
                    ]);
                }
            } else {
                $this->servers = [];
                $this->dispatch('notify', [
                    'type' => 'warning',
                    'message' => 'Không có dữ liệu server nào được tìm thấy. Vui lòng thử lại sau.'
                ]);
            }
        } catch (\Exception $e) {
            $this->servers = [];
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Lỗi khi tải dữ liệu: ' . $e->getMessage()
            ]);
        }
    }

    // Phương thức xử lý bật server
    public function powerOn($ip)
    {
        return $this->handlePowerAction($ip, 'on');
    }

    // Phương thức xử lý tắt server
    public function powerOff($ip)
    {
        return $this->handlePowerAction($ip, 'off');
    }

    // Phương thức xử lý khởi động lại server
    public function powerRestart($ip)
    {
        return $this->handlePowerAction($ip, 'restart');
    }

    // Phương thức chung xử lý các hành động power
    private function handlePowerAction($ip, $action)
    {
        try {
            // Make HTTP request to the power action endpoint using Laravel's HTTP client
            $response = Http::get(url("/api/ipmi/power/{$ip}/{$action}"));
            $result = $response->json();

            $this->dispatch('notify', [
                'type' => $result['status'] === 'success' ? 'success' : 'error',
                'message' => 'Power action ' . $action . ' ' . ($result['status'] === 'success' ? 'đã được gửi' : 'thất bại')
            ]);

            // Refresh data after performing the action
            $this->loadData();

            return $result;
        } catch (\Exception $e) {
            $this->dispatch('notify', [
                'type' => 'error',
                'message' => 'Lỗi: ' . $e->getMessage()
            ]);
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function render()
    {
        $filteredServers = [];

        if (!empty($this->servers) && is_array($this->servers)) {
            $searchTerm = strtolower(trim($this->search));

            $filteredServers = array_filter($this->servers, function($server) use ($searchTerm) {
                if (!is_array($server)) {
                    return false;
                }

                $name = strtolower($server['name'] ?? '');
                $ip = $server['ip'] ?? '';

                return empty($searchTerm) ||
                       str_contains($name, $searchTerm) ||
                       str_contains($ip, $searchTerm);
            });

            // Reset array keys to ensure proper JSON encoding
            $filteredServers = array_values($filteredServers);
        }

        return view('livewire.server-grid', [
            'filteredServers' => $filteredServers
        ]);
    }
}
