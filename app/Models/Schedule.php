<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'field_id',
        'start_time',
        'end_time',
        'status',
        'locked_until',
    ];

    /**
     * Status slot yang valid
     */
    public const STATUSES = [
        'available',
        'locked',
        'booked',
        'maintenance',
    ];

    protected $casts = [
        'start_time'   => 'datetime',
        'end_time'     => 'datetime',
        'locked_until' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked'
            && $this->locked_until
            && $this->locked_until->isFuture();
    }


    public function isBooked(): bool
    {
        return $this->status === 'booked';
    }

    public function canBeLocked(): bool
    {
        if ($this->status === 'available') {
            return true;
        }

        if ($this->status === 'locked' && $this->locked_until && $this->locked_until->isPast()) {
            return true;
        }

        return false;
    }

    public function unlock(): void
    {
        $this->update([
            'status' => 'available',
            'locked_until' => null,
        ]);
    }



    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function field()
    {
        return $this->belongsTo(Field::class);
    }

    public function bookingItem()
    {
        return $this->hasOne(BookingItem::class);
    }
}
