<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MonitoringRecord extends Model
{
    use HasFactory;

    protected $table = 'monitoring_records';

    protected $fillable = [
        'tree_id',
        'assignment_id',
        'couple_user_id',
        'photo',
        'status',
        'checked_at',
        'synced_at',
    ];

    protected $casts = [
        'status' => 'string',
        'checked_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function tree()
    {
        return $this->belongsTo(Tree::class);
    }

    public function assignment()
    {
        return $this->belongsTo(MonitoringAssignment::class, 'assignment_id');
    }

    public function coupleUser()
    {
        return $this->belongsTo(User::class, 'couple_user_id');
    }
}
