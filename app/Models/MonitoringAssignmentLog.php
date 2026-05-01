<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MonitoringAssignmentLog extends Model
{
    use HasFactory;

    protected $table = 'monitoring_assignment_logs';

    protected $fillable = [
        'assignment_id',
        'previous_staff_id',
        'new_staff_id',
        'remarks',
        'transferred_by',
        'transferred_at',
    ];

    protected $casts = [
        'transferred_at' => 'datetime',
    ];

    public function assignment()
    {
        return $this->belongsTo(MonitoringAssignment::class);
    }

    public function previousStaff()
    {
        return $this->belongsTo(User::class, 'previous_staff_id');
    }

    public function newStaff()
    {
        return $this->belongsTo(User::class, 'new_staff_id');
    }

    public function transferredBy()
    {
        return $this->belongsTo(User::class, 'transferred_by');
    }
}
