<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB; // <--- WAJIB TAMBAHKAN INI

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
        'payment_proof',
    ];

    public function getRouteKeyName()
    {
        return 'booking_code';
    }

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

    protected $appends = ['payment_proof_url'];

    /*
    |--------------------------------------------------------------------------
    | Status Helpers & Real-time Logic
    |--------------------------------------------------------------------------
    */

    /**
     * Logic Otomatis: Update status booking & kunci jadwal lapangan
     */
    public function markAsPaid()
    {
        return DB::transaction(function () {
            // 1. Update header booking jadi Lunas
            $this->update(['payment_status' => 'paid']);

            // 2. Cari semua item (jadwal) yang dipesan di booking ini, lalu kunci statusnya
            foreach ($this->items as $item) {
                if ($item->schedule) {
                    $item->schedule->update(['status' => 'booked']);
                }
            }
            
            return $this;
        });
    }

    public function getPaymentProofUrlAttribute()
    {
        if (!$this->payment_proof) return null;
        return asset('storage/' . $this->payment_proof);
    }

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