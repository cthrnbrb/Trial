<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'org_name',
        'president_first_name',
        'president_middle_name',
        'president_last_name',
        'president_email',
        'organization_code',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function plantingActivities()
    {
        return $this->hasMany(PlantingActivity::class, 'organization_id');
    }
}
