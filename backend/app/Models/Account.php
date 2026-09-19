<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Account extends Model
{
    use HasUuids;

    protected $fillable = [
        'numero_cuenta',
        'saldo_cache',
        'version',
        'last_synced_at',
    ];

    protected $casts = [
        'saldo_cache' => 'decimal:2',
        'last_synced_at' => 'datetime',
    ];

    public function transaccionesOrigen()
    {
        return $this->hasMany(Transaction::class, 'cuenta_origen_id');
    }

    public function transaccionesDestino()
    {
        return $this->hasMany(Transaction::class, 'cuenta_destino_id');
    }
}