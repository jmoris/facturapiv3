<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfigContribuyente extends Model
{
    use HasFactory;

    public function contribuyente()
    {
        return $this->belongsTo(contribuyente::class);
    }
}
