<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Couple extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'or_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
