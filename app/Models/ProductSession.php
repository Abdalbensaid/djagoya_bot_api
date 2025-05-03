<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductSession extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'step',
        'data',
    ];
    protected $casts = [
        'data' => 'array', // Cast JSON to array
    ];
}
