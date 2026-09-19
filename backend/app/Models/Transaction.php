<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'idempotency_key',
        'cuenta_origen_id',
        'cuenta_destino_id',
        'referencia_externa',
        'monto',
        'moneda',
        'estado',
        'correlation_id',
        'motivo_fallo',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
    ];

    public function cuentaOrigen()
    {
        return $this->belongsTo(Account::class, 'cuenta_origen_id');
    }

    public function cuentaDestino()
    {
        return $this->belongsTo(Account::class, 'cuenta_destino_id');
    }

    public function aiRecommendation()
    {
        return $this->hasOne(AiRecommendation::class);
    }
}