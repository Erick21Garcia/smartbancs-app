<?php

namespace App\Console\Commands;

use App\Models\Account;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DemoDeadlock extends Command
{
    protected $signature = 'demo:deadlock {orden}';
    protected $description = 'Fuerza un deadlock real de PostgreSQL para demostrar detección (solo demo, no producción)';

    public function handle(): int
    {
        $cuentas = Account::orderBy('numero_cuenta')->take(2)->pluck('id')->toArray();
        [$cuentaA, $cuentaB] = $cuentas;

        $orden = $this->argument('orden');
        $primero = $orden === '1' ? $cuentaA : $cuentaB;
        $segundo = $orden === '1' ? $cuentaB : $cuentaA;

        $this->info("Proceso {$orden}: bloqueando {$primero} primero...");

        try {
            DB::transaction(function () use ($primero, $segundo, $orden) {
                DB::table('accounts')->where('id', $primero)->lockForUpdate()->first();

                Log::channel('transactions')->info('demo_deadlock.lock_1_acquired', [
                    'proceso' => $orden,
                    'cuenta' => $primero,
                ]);

                $this->info("Proceso {$orden}: lock 1 adquirido, esperando 3s...");
                sleep(3);

                $this->info("Proceso {$orden}: intentando bloquear {$segundo}...");
                DB::table('accounts')->where('id', $segundo)->lockForUpdate()->first();

                Log::channel('transactions')->info('demo_deadlock.lock_2_acquired', [
                    'proceso' => $orden,
                    'cuenta' => $segundo,
                ]);
            });

            $this->info("Proceso {$orden}: completado sin deadlock.");
        } catch (\Illuminate\Database\QueryException $e) {
            Log::channel('transactions')->error('demo_deadlock.detectado', [
                'proceso' => $orden,
                'sqlstate' => $e->getCode(),
                'mensaje' => $e->getMessage(),
            ]);

            $this->error("Proceso {$orden}: DEADLOCK DETECTADO — {$e->getMessage()}");
        }

        return 0;
    }
}