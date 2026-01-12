<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Field extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venue_id',
        'name',
        'type',
        'price_per_hour',
        'image',
        'is_active',
    ];

    /**
     * Jenis lapangan yang diizinkan
     */
    public const TYPES = [
        'futsal',
        'badminton',
        'basket',
        'tennis',
    ];

    protected $casts = [
        'price_per_hour' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }
}
