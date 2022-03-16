<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InfoContribuyente extends Model
{
    use HasFactory;

    public function actecos(){
        return $this->belongsToMany(Acteco::class, 'acteco_info_contribuyentes', 'ref_icontribuyente', 'ref_acteco');
    }
}
