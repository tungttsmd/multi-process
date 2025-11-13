<?php

namespace App\Services;

use App\Models\Host;

class HostService
{
    private $data;
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        $this->data = Host::get();
    }
    public function all() {
        return $this->data;
    }

    public function ip($ip) {
        return Host::where('ip', $ip)->first();
    }
}
