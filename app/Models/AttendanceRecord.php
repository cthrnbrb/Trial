<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceRecord extends Model
{
    use HasFactory;

    protected $table = 'attendance_records';

    protected $fillable = [
        'activity_id',
        'user_id',
        'attendance',
        'tree_id',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * The planting activity this attendance record belongs to
     */
    public function activity()
    {
        return $this->belongsTo(PlantingActivity::class, 'activity_id');
    }

    /**
     * The user (participant) for this attendance
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The tree associated with this attendance (proof of participation)
     */
    public function tree()
    {
        return $this->belongsTo(Tree::class, 'tree_id');
    }
}
