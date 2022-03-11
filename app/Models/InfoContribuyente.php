<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfoContribuyente extends Model
{
    use HasFactory;

    protected $primaryKey = 'rut';

    public $incrementing = false;
}
