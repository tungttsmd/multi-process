<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\PowerResource;
use App\Models\Power;
use Illuminate\Http\Request;

class PowerController extends Controller
{
    public function index() {
        $powers = Power::get();
        return PowerResource::collection($powers);
    }

    public function get(Request $request)
    {
        $power = Power::where('ip', $request->ip)->get();

        return PowerResource::collection($power);
    }

}
