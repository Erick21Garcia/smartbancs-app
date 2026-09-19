<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Jobs\ProcessAiRecommendation;
use App\Services\MetricsService;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TransactionController extends Controller
{
    public function store(StoreTransactionRequest $request)
    {
        $inicio = microtime(true);
        $correlationId = (string) Str::uuid();

        Log::channel('transactions')->info('transaction.received', [
            'correlation_id' => $correlationId,
            'idempotency_key' => $request->idempotency_key,
            'cuenta_origen_id' => $request->cuenta_origen_id,
            'monto' => $request->monto,
        ]);

        // 1. Idempotencia: si ya procesamos esta clave, devolvemos el resultado guardado
        $existente = Transaction::where('idempotency_key', $request->idempotency_key)->first();
        if ($existente) {

            Log::channel('transactions')->info('transaction.idempotent_hit', [
                'correlation_id' => $correlationId,
                'transaction_id' => $existente->id,
            ]);

            MetricsService::incrementCounter(
                'transactions_total', 'Total de transacciones procesadas',
                ['resultado'], ['idempotent_hit']
            );

            return response()->json($existente, 200);
        }

        try {
            $transaction = DB::transaction(function () use ($request, $correlationId) {

                // 2. Bloqueo pesimista sobre la cuenta origen
                $cuentaOrigen = Account::where('id', $request->cuenta_origen_id)
                    ->lockForUpdate()
                    ->firstOrFail();

                Log::channel('transactions')->info('transaction.lock_acquired', [
                    'correlation_id' => $correlationId,
                    'cuenta_origen_id' => $cuentaOrigen->id,
                ]);

                // 3. Validar saldo suficiente
                if ($cuentaOrigen->saldo_cache < $request->monto) {
                    Log::channel('transactions')->warning('transaction.saldo_insuficiente', [
                        'correlation_id' => $correlationId,
                        'cuenta_origen_id' => $cuentaOrigen->id,
                        'saldo_actual' => $cuentaOrigen->saldo_cache,
                        'monto_solicitado' => $request->monto,
                    ]);
                    throw new \RuntimeException('SALDO_INSUFICIENTE');
                }

                // 4. Descontar saldo y registrar la transacción
                $cuentaOrigen->decrement('saldo_cache', $request->monto);
                $cuentaOrigen->increment('version');

                $nuevaTransaction = Transaction::create([
                    'idempotency_key' => $request->idempotency_key,
                    'cuenta_origen_id' => $request->cuenta_origen_id,
                    'cuenta_destino_id' => $request->cuenta_destino_id,
                    'referencia_externa' => $request->referencia_externa,
                    'monto' => $request->monto,
                    'moneda' => $request->moneda ?? 'USD',
                    'estado' => 'completed',
                    'correlation_id' => $correlationId,
                ]);

                Log::channel('transactions')->info('transaction.completed', [
                    'correlation_id' => $correlationId,
                    'transaction_id' => $nuevaTransaction->id,
                ]);

                return $nuevaTransaction;
            });

            ProcessAiRecommendation::dispatch($transaction->id, $correlationId);

            Log::channel('transactions')->info('transaction.ai_dispatched', [
                'correlation_id' => $correlationId,
                'transaction_id' => $transaction->id,
            ]);

            $duracion = microtime(true) - $inicio;

            MetricsService::incrementCounter(
                'transactions_total', 'Total de transacciones procesadas',
                ['resultado'], ['completed']
            );

            MetricsService::observeHistogram(
                'transaction_duration_seconds', 'Duración del procesamiento de una transacción',
                $duracion
            );

            return response()->json($transaction, 201);

        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'SALDO_INSUFICIENTE') {

                MetricsService::incrementCounter(
                    'transactions_total', 'Total de transacciones procesadas',
                    ['resultado'], ['saldo_insuficiente']
                );

                return response()->json([
                    'error' => 'Saldo insuficiente',
                    'correlation_id' => $correlationId,
                ], 422);
            }
            throw $e;
        } catch (\Throwable $e) {
            Log::channel('transactions')->error('transaction.error', [
                'correlation_id' => $correlationId,
                'exception' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function show(Transaction $transaction)
    {
        return response()->json($transaction->load('aiRecommendation'));
    }
}