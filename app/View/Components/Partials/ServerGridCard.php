<?php

namespace App\View\Components\Partials;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ServerGridCard extends Component
{
    public $item;
    /**
     * Create a new component instance.
     */
    public function __construct($item)
    {
        $this->item = $item;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
         $t0 = $this->item->sensor_log->data->CPU0_Temp ?? null;
         $t1 = $this->item->sensor_log->data->CPU1_Temp ?? null;
         $f0 = $this->item->sensor_log->data->CPU0_FAN ?? null;
         $f1 = $this->item->sensor_log->data->CPU1_FAN ?? null;
         $power = $this->item->power_log->data->power ?? null;

         // Màu CPU
         $colorTemp = fn($t) => $t < 75 ? 'green' : ($t < 80 ? 'yellow' : 'red');

         // Chọn màu nền theo mức nhiệt độ cao nhất
         $maxColor = 'green';
         foreach ([$t0, $t1] as $temp) {
             $c = $colorTemp($temp);
             if ($c === 'red') {
                 $maxColor = 'red';
                 break;
             } elseif ($c === 'yellow' && $maxColor !== 'red') {
                 $maxColor = 'yellow';
             }
         }

         // Áp màu nền rất nhạt (dạng pastel)
         $bgClass = match ($maxColor) {
             'red' => 'bg-red-100 border-red-300',
             'yellow' => 'bg-yellow-50 border-yellow-200',
             default => 'bg-green-50 border-green-100',
         };

         // Màu vòng tròn
         $colorTempClass = fn($t) => $t < 75
             ? 'bg-green-500 shadow-[0_0_6px_1px_rgba(34,197,94,0.3)]'
             : ($t < 80
                 ? 'bg-yellow-500 shadow-[0_0_6px_1px_rgba(234,179,8,0.3)]'
                 : 'bg-red-500 shadow-[0_0_8px_2px_rgba(239,68,68,0.5)]');

         $colorFan = fn($rpm) => !$rpm
             ? 'bg-gray-400'
             : ($rpm < 960
                 ? 'bg-red-500 shadow-[0_0_6px_1px_rgba(239,68,68,0.4)]'
                 : ($rpm < 1040
                     ? 'bg-yellow-400 shadow-[0_0_6px_1px_rgba(234,179,8,0.4)]'
                     : 'bg-green-500 shadow-[0_0_6px_1px_rgba(34,197,94,0.4)]'));

         // Power level
         $powerLevel = match (strtolower($power ?? '')) {
             'on' => 3,
             'off' => 0,
             default => null,
         };

         $isInactive = is_null($powerLevel) || $powerLevel === 0;
        return view('components.partials.server-grid-card',
        compact(
            't0', 't1', 'f0', 'f1', 'bgClass','colorTempClass', 'colorFan', 'powerLevel', 'isInactive'
        ));
    }
}
