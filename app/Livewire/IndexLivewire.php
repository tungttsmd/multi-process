<?php

namespace App\Livewire;

use App\Models\Host;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Livewire\Component;

class IndexLivewire extends Component
{
    /**
     * Biến cập nhật toàn bộ thông tin chính của livewire lên render view
     * @var array
     */
    public $fetch;

    /**
     * Biến debug chỉ dùng khi dev
     * @var array
     */
    public $debug;
public $viewMode = 'grid'; // hoặc 'table'

    /**
     * Hàm khởi tạo:
     * - Khởi tạo cập nhật mới toàn bộ thông tin máy chủ vào $fetch
     *
     * @return void
     */
    public function mount() {
        $this->fetchRefresh();
    }

    /**
     * Render view chính của livewire
     *
     * @return void
     */
    public function render()
    {
        return view('livewire.index');
    }


public function toggleView()
{
    $this->viewMode = $this->viewMode === 'grid' ? 'table' : 'grid';
}

    /**
     * Cập nhật toàn bộ máy chủ vào $fetch
     *
     * @return void
     */
    public function fetchRefresh() {
        // Collection
        $host = Host::with('sensor', 'power')->orderBy('name')->get();

        // Object
        $this->fetch = $this->mapReturn(collectionToObject($host));
    }

    /**
     * Cập nhật chính xác 1 máy chủ vào $fetch
     * Sử dụng button wire:click="fetchUpdateExactly('192.162.6.14')"
     * Đồng thời khối có wire:key sẽ được cập nhật cục bộ, không làm hỏng trang
     *
     * @param  string  $ip
     * @return void
     */
    public function fetchUpdateExactly($ip) {
        foreach ($this->fetch as $index => $item) {
            if ($item->ip === $ip) {
                $this->fetch[$index] = $this->fetchRefreshOne($ip);
                break;
            }
        }
    }

    /**
     * Hàm lấy thông tin chính xác 1 máy chủ (Hỗ trợ cho hàm fetchUpdateExactly)
     *
     * @param  string  $ip
     * @return void
     */
    public function fetchRefreshOne($ip) {
        // Gọi job cập nhật dữ liệu
        Artisan::call("ipmi:sensor host:$ip");
        Artisan::call("ipmi:redis host:$ip");

        // Lưu lại sensor_time ban đầu để so sánh thay đổi
        $ori_host = Host::with('sensor', 'power')->where('ip', $ip)->first();
        $ori_time = $ori_host->sensor->updated_at ?? null;

        // Tối đa 6 lần thử (mỗi lần cách 1 giây)
        for ($i = 0; $i < 6; $i++) {
            sleep(1); // chờ job cập nhật

            $host = Host::with('sensor', 'power')->where('ip', $ip)->first();
            $new_time = $host->sensor->updated_at ?? null;

            // Nếu thời gian cập nhật khác → dữ liệu mới đã có
            if ($new_time !== $ori_time) {
                return $this->mapReturn(collectionToObject($host), true);
            }
        }

        // Nếu sau 5 lần vẫn chưa có thay đổi → trả về dữ liệu hiện tại
        return $this->mapReturn(collectionToObject($ori_host), true);
    }



    /**
     * Action power
     */
    public function powerAction($ip, $action) {
        if ($action === 'on') {
            // Gọi job thực hiện lệnh
            Artisan::call("ipmi:execute on:$ip");
        } elseif ($action === 'off') {
            Artisan::call("ipmi:execute off:$ip");
        } else {
            Artisan::call("ipmi:execute reset:$ip");
        }
    }

    /**
     * Kiểm tra xem input có phải là object hoặc null hay không.
     * Nếu không phải, ném lỗi InvalidArgumentException.
     *
     * @param  object  $thisInput
     * @param  bool  $step  (tuỳ chọn, để dùng mapReturn lên object một lớp true hay nhiều lớp false)
     * @return object|null
     */
    public function mapReturn(object $thisInput, $step = false) {

        if ($step === false) {

            $result = [];
            foreach ($thisInput as $item) {
                $result[] = (object) [
                    'ip' => $item->ip,
                    'name' => $item->name,
                    'username' => $item->username,
                    'password' => $item->password,
                    'sensor_log' => $this->decodeLog($item->sensor->log),
                    'power_log' => $this->decodeLog($item->power->log),
                    'sensor_time' => $item->sensor->updated_at,
                    'power_time' => $item->power->updated_at,
                ];
            }
            return $result;
        }

        if ($step === true) {
            return (object) [
                    'ip' =>  $thisInput->ip,
                    'name' => $thisInput->name,
                    'username' => $thisInput->username,
                    'password' => $thisInput->password,
                    'sensor_log' => $this->decodeLog($thisInput->sensor->log),
                    'power_log' => $this->decodeLog($thisInput->power->log),
                    'sensor_time' => $thisInput->sensor->updated_at,
                    'power_time' => $thisInput->power->updated_at,
            ];
        }
    }

     /**
     * Kiểm tra xem input có phải là string hoặc null hay không.
     * Nếu không phải, ném lỗi InvalidArgumentException.
     *
     * @param  mixed  $json
     * @param  string|null  $paramName  (tuỳ chọn, để hiển thị tên biến trong lỗi)
     * @return string|null
     *
     * @throws \InvalidArgumentException
     */
    public function decodeLog($json)
    {
        if (is_null($json)) {
            return null;
        } elseif (is_string($json)) {
            $json = preg_replace('/[^\x20-\x7E]/', '', $json);
            return(object) json_decode(json_decode(preg_replace('/\s{2,}/', ' ', $json)));
        }

        $name = '$json' ? "tham số ".'{$json}'.'"' : 'tham số';
        throw new InvalidArgumentException(sprintf(
            '$this->decodeLog($json): '."%s phải là kiểu string hoặc null, %s được truyền vào.",
            is_object($json) ? get_class($json) : gettype($json)
        ));
    }
}
