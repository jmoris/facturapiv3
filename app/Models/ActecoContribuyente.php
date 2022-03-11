<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActecoContribuyente extends Model
{
    use HasFactory;

    public function contribuyente()
    {
        return $this->belongsToMany(Contribuyente::class);
    }
}
