<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class ExecuteController extends Controller
{
    public function index(Request $request)
    {
        if (in_array($request->io, ['on', 'off', 'reset']) && !is_null($request->ip)) {
            set_time_limit(12);
            $exitcode = Artisan::call("ipmi:execute {$request->io}:{$request->ip}");
            $output = trim(Artisan::output());
        }

        // Trả JSON kết quả
        return response()->json([
            'ip' => $request->ip,
            'io' => $request->io,
            'exit_code' => $exitcode ?? null,
            'output' => $output ?? null,
        ]);
    }
}

