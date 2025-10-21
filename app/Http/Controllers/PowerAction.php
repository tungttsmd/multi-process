<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class PowerAction extends Controller
{
    public function action($ip, $action) {
        Artisan::call('ipmi:power '. "$action:$ip");
        $output = Artisan::output();

        return response()->json([
            'status' => 'success',
            'action' => $action,
            'ip' => $ip,
            'output' => $output,
        ]);
    }
}
