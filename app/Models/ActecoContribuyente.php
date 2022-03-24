<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActecoContribuyente extends Model
{
    use HasFactory;
    protected $hidden = ['pivot'];

    public function contribuyentes(){
        return $this->belongsToMany(InfoContribuyente::class, 'acteco_info_contribuyentes', 'ref_acteco', 'ref_icontribuyente');
    }
}
