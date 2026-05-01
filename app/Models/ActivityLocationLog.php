<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLocationLog extends Model
{
    use HasFactory;

    protected $table = 'activity_location_logs';

    protected $fillable = [
        'activity_id',
        'old_barangay_id',
        'new_barangay_id',
        'old_lat',
        'old_lng',
        'new_lat',
        'new_lng',
        'remarks',
        'changed_by',
        'changed_at',
    ];

    protected $casts = [
        'old_lat' => 'decimal:7',
        'old_lng' => 'decimal:7',
        'new_lat' => 'decimal:7',
        'new_lng' => 'decimal:7',
        'changed_at' => 'datetime',
    ];

    public function activity()
    {
        return $this->belongsTo(PlantingActivity::class);
    }

    public function oldBarangay()
    {
        return $this->belongsTo(Barangay::class, 'old_barangay_id');
    }

    public function newBarangay()
    {
        return $this->belongsTo(Barangay::class, 'new_barangay_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
