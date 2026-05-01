<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PlantingActivity extends Model
{
    use HasFactory;

    protected $table = 'planting_activities';

    protected $fillable = [
        'organization_id',
        'barangay_id',
        'site_name',
        'tree_species',
        'center_lat',
        'center_lng',
        'radius_meters',
        'scheduled_date',
        'status',
        'is_deleted',
        'deleted_at',
        'deleted_by',
    ];

    protected $casts = [
        'scheduled_date' => 'date',
        'center_lat' => 'decimal:7',
        'center_lng' => 'decimal:7',
        'is_deleted' => 'boolean',
        'deleted_at' => 'datetime',
    ];

    public function organization()
    {
        return $this->belongsTo(Organization::class, 'organization_id');
    }

    public function barangay()
    {
        return $this->belongsTo(Barangay::class, 'barangay_id');
    }

    public function trees()
    {
        return $this->hasMany(Tree::class, 'activity_id');
    }

    public function monitoringAssignments()
    {
        return $this->hasMany(MonitoringAssignment::class, 'activity_id');
    }

    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class, 'activity_id');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
