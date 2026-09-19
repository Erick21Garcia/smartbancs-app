<?php

namespace App\Jobs;

use App\Models\AiRecommendation;
use App\Models\Transaction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAiRecommendation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $transactionId,
        public string $correlationId,
    ) {}

    public function handle(): void
    {
        $transaction = Transaction::findOrFail($this->transactionId);

        $recommendation = AiRecommendation::create([
            'transaction_id' => $transaction->id,
            'estado' => 'pending',
            'payload' => [
                'monto' => $transaction->monto,
                'cuenta_origen_id' => $transaction->cuenta_origen_id,
            ],
        ]);

        Log::channel('ai_recommendation.processing', [
            'correlation_id' => $this->correlationId,
            'transaction_id' => $transaction->id,
        ]);

        // Mock de IA: reglas simples en vez de un modelo real
        $resultado = $this->generarRecomendacionMock((float) $transaction->monto);

        $recommendation->update([
            'estado' => 'completed',
            'recomendacion' => $resultado,
        ]);

        Log::channel('ai_recommendation.completed', [
            'correlation_id' => $this->correlationId,
            'transaction_id' => $transaction->id,
        ]);
    }

    private function generarRecomendacionMock(float $monto): array
    {
        if ($monto > 500) {
            return [
                'tipo' => 'alerta_gasto_alto',
                'mensaje' => 'Este monto es considerablemente alto comparado con tu actividad reciente.',
            ];
        }

        return [
            'tipo' => 'informativo',
            'mensaje' => 'Transacción dentro de tu rango habitual de gasto.',
        ];
    }
}