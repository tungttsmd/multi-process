<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatusFetcher extends Controller
{
    public function get($ip)  {
    $data = DB::table('statuses')
        ->select('log')
        ->where('ip', $ip)
        ->first();
    if (!$data) {
        return response()->json(['error' => "Không tìm thấy log cho $ip"], 404);
    }

    $json = json_decode(trim($data->log));
    $return = json_decode($json);
    return $return;

    }
}
