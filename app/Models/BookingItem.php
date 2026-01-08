<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BookingItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'booking_id',
        'schedule_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
