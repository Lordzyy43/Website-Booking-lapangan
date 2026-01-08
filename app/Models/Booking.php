<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Booking extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'booking_code',
        'total_amount',
        'payment_status',
        'payment_token',
        'expired_at',
    ];

    /**
     * Status pembayaran yang valid
     */
    public const PAYMENT_STATUSES = [
        'unpaid',
        'paid',
        'expired',
        'cancelled',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'expired_at'   => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isUnpaid(): bool
    {
        return $this->payment_status === 'unpaid';
    }

    public function isExpired(): bool
    {
        return $this->payment_status === 'expired';
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }
}
