<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DireccionContribuyente extends Model
{
    use HasFactory;

    protected $hidden = ['pivot'];

    public function contribuyente()
    {
        return $this->belongsTo(Contribuyente::class);
    }

    public function comuna(){
        return $this->hasOne(Comuna::class, 'id', 'ref_comuna');
    }
}
