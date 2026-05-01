<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'email',
        'password',
        'first_name',
        'middle_name',
        'last_name',
        'contact_number',
        'address',
        'or_number',
        'photo',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'password' => 'hashed',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
        'deleted_by' => 'integer',
    ];

    public function getNameAttribute(): string
    {
        return $this->first_name . ' ' . ($this->middle_name ? $this->middle_name . ' ' : '') . $this->last_name;
    }

    public function userOrganizations()
    {
        return $this->hasMany(UserOrganization::class);
    }

    public function couple()
    {
        return $this->hasOne(Couple::class);
    }

    public function treesAsPlanter()
    {
        return $this->hasMany(Tree::class, 'planter_id');
    }

    public function monitoringAssignmentsAsStaff()
    {
        return $this->hasMany(MonitoringAssignment::class, 'staff_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

}
