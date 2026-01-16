<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'created_by',
        'name',
        'slug',
        'address',
        'image',
        'description',
        'open_time',
        'close_time',
    ];

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    protected function image(): Attribute
    {
        return Attribute::make(
            get: fn ($value) =>
                $value
                    ? (filter_var($value, FILTER_VALIDATE_URL)
                        ? $value
                        : asset('storage/' . $value))
                    : null
        );
    }

    protected function openTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('H:i', strtotime($value)) : null
        );
    }

    protected function closeTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('H:i', strtotime($value)) : null
        );
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($venue) {
            // 1. Catat admin yang buat
            if (auth()->check()) {
                $venue->created_by = auth()->id();
            }

            // 2. TIMPA PAKSA ke 3. Jangan pakai "if (!$venue->owner_id)"
            // Dengan begini, apa pun yang dikirim dari Frontend/Controller akan dibuang dan diganti 3.
            $venue->owner_id = 3;
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Admin pembuat venue
    public function admin()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Owner pemilik venue (laporan & uang)
    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }
}
