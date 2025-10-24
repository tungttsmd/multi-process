<?php

namespace App\Livewire;

use App\Http\Controllers\PowerFetcher;
use App\Models\Host;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Livewire\Component;

class IndexLivewire extends Component
{
    public $fetch;

    public function mount() {
        // Collection
        $host = Host::with('sensor', 'power')->get();
        $this->fetch = $this->mapReturn(collectionToObject($host));
    }

    /**
     * Dữ liệu truyền ẩn $fetch: dữ liệu object về Nhiệt độ, trạng thái của máy theo ip, name
     */
    public function render()
    {
        return view('livewire.index');
    }

    public function powerAction($action) {
        if ($action === 'on') {

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

    public function mapReturn(object $thisInput) {
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
                'power_time' => $item->sensor->updated_at,
            ];
        }
        return $result;
    }
}
