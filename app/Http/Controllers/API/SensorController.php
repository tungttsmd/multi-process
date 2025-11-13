<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\SensorResource;
use App\Models\Sensor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class SensorController extends Controller
{
    public function index() {
        $sensors = Sensor::get();
        return SensorResource::collection($sensors);
    }

    public function get(Request $request)
    {
        $sensor = Sensor::where('ip', $request->ip)->get();

        return SensorResource::collection($sensor);
    }
}
