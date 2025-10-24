<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Host extends Model
{
    protected $table = 'hosts';
    protected $fillable = ['name', 'ip'];
    public function sensor()
    {
        return $this->hasOne(Sensor::class, 'ip', 'ip');
    }

    public function power()
    {
        return $this->hasOne(Power::class, 'ip', 'ip');
    }
}
