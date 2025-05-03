<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = ['user_id', 'name', 'description', 'price', 'image'];

    protected $casts = [
    'price' => 'float',
    'image' => 'array' // Si vous stockez plusieurs images
];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

