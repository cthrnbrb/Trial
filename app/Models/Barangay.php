<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Barangay extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'municipality',
        'province',
        'boundary',
    ];

    public function zones()
    {
        return $this->hasMany(Zone::class);
    }

    public function plantingActivities()
    {
        return $this->hasMany(PlantingActivity::class);
    }
}
