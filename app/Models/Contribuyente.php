<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contribuyente extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'rut',
        'razon_social',
        'ambiente',
        'nro_resolucion_prod',
        'fch_resolucion_prod',
        'nro_resolucion_dev',
        'fch_resolucion_dev',
        'telefono',
        'mail',
        'web'
    ];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'contribuyentes';


    public function users()
    {
        return $this->belongsToMany(User::class, 'contribuyentes_users');
    }

    public function config(){
        return $this->hasOne(ConfigContribuyente::class, 'ref_contribuyente', 'id');
    }

    public function direcciones(){
        return $this->hasMany(DireccionContribuyente::class, 'ref_contribuyente', 'id');
    }

    public function actecos(){
        return $this->belongsToMany(Acteco::class, 'acteco_contribuyentes', 'ref_contribuyente', 'ref_acteco');
    }

    public function documentos(){
        return $this->hasMany(Documento::class, 'ref_contribuyente', 'id');
    }

    public function rcofs(){
        return $this->hasMany(RCOF::class, 'ref_contribuyente', 'id');
    }
}
