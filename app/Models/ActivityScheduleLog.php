<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityScheduleLog extends Model
{
    use HasFactory;

    protected $table = 'activity_schedule_logs';

    protected $fillable = [
        'activity_id',
        'old_date',
        'new_date',
        'remarks',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'old_date' => 'date',
        'new_date' => 'date',
        'changed_at' => 'datetime',
    ];

    public function activity()
    {
        return $this->belongsTo(PlantingActivity::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
