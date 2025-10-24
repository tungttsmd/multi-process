<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Power extends Model
{
    protected $table = 'powers';
    protected $fillable = ['ip', 'power_data'];

    public function host()
    {
        return $this->belongsTo(Host::class, 'ip', 'ip');
    }
}
