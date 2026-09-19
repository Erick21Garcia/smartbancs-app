<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AiRecommendation extends Model
{
    use HasUuids;

    protected $fillable = [
        'transaction_id',
        'estado',
        'payload',
        'recomendacion',
    ];

    protected $casts = [
        'payload' => 'array',
        'recomendacion' => 'array',
    ];

    public function transaction()
    {
        return $this->belongsTo(Transaction::class);
    }
}