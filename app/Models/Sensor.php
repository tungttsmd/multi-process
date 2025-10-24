<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Sensor extends Model
{
    protected $fillable = ['ip', 'sensor_data'];
    
    public function host()
    {
        return $this->belongsTo(Host::class, 'ip', 'ip');
    }
}
