<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** XS, S, M… El orden fija cómo se muestran. */
class Talla extends Model
{
    use HasFactory;

    protected $table = 'tallas';

    protected $guarded = ['id'];
}
