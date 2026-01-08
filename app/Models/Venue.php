<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Pemilik venue (owner)
    public function admin()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Venue punya banyak lapangan
    public function fields()
    {
        return $this->hasMany(Field::class);
    }
}
