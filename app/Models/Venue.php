<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute; // Penting untuk Accessor
use Illuminate\Support\Facades\Storage;

class Venue extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'address',
        'image',
        'description',
        'open_time',
        'close_time',
    ];

    /**
     * Accessor: Memastikan React selalu menerima URL Gambar yang lengkap.
     * Jadi di database simpan "venues/file.jpg", di React jadi "http://localhost:8000/storage/venues/file.jpg"
     */
    protected function image(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if (!$value) return null;
                // Jika sudah berupa URL (http...), langsung kembalikan
                if (filter_var($value, FILTER_VALIDATE_URL)) return $value;
                // Jika hanya path, bungkus dengan URL storage
                return asset('storage/' . $value);
            }
        );
    }

    /**
     * Accessor: Memastikan format waktu konsisten HH:mm
     */
    protected function openTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('H:i', strtotime($value)) : null,
        );
    }

    protected function closeTime(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? date('H:i', strtotime($value)) : null,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function fields()
    {
        return $this->hasMany(Field::class);
    }
}