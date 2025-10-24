<?php

namespace App\Livewire;

use App\Models\Host;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class IndexLivewire extends Component
{
    public $fetch;

    public function mount() {
        $host = Host::with('sensor', 'power')->first();
        dd($host);
    }

    public function render()
    {
        return view('livewire.index');
    }

    public function decodePowerLog(string $json)
    {
        $json = preg_replace('/[^\x20-\x7E]/', '', $json);
        return preg_replace('/\s{2,}/', ' ', $json);
    }

    public function decodeSensorLog(string $json)
    {
        $json = preg_replace('/[^\x20-\x7E]/', '', $json);
        $json = preg_replace('/\s{2,}/', ' ', $json);
    }

    public function getPowers() {
        return DB::table('statuses')
            ->select('log','ip')
            ->get();
    }

    public function getSensors() {
        return DB::table('sensors')
            ->select('log','ip')
            ->get();
    }

    public function getHosts() {
        return DB::table('hosts')
            ->select('name','ip')
            ->orderBy('name', 'asc')
            ->get();
    }
}
