<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class TransformTransactionsEtl extends Command
{
    protected $signature = 'etl:transform-transactions
                            {input=storage/app/etl/transacciones_raw.csv}
                            {output=storage/app/etl/transacciones_procesadas.json}';

    protected $description = 'ETL: limpia y estructura un lote de transacciones crudas para consumo de la IA';

    public function handle(): int
    {
        $rutaEntrada = base_path($this->argument('input'));
        $rutaSalida = base_path($this->argument('output'));

        if (!file_exists($rutaEntrada)) {
            $this->error("Archivo de entrada no encontrado: {$rutaEntrada}");
            return 1;
        }

        $filas = array_map('str_getcsv', file($rutaEntrada));
        $encabezados = array_shift($filas);

        $registrosLimpios = [];
        $descartados = 0;

        foreach ($filas as $fila) {
            $registro = array_combine($encabezados, $fila);
            $limpio = $this->limpiarRegistro($registro);

            if ($limpio === null) {
                $descartados++;
                continue;
            }

            $registrosLimpios[] = $limpio;
        }

        $this->info("Registros procesados: " . count($registrosLimpios) . " | Descartados por datos inválidos: {$descartados}");

        $agregadosPorCuenta = $this->agregarPorCuenta($registrosLimpios);

        $resultado = [
            'generado_en' => now()->toIso8601String(),
            'total_transacciones' => count($registrosLimpios),
            'transacciones' => $registrosLimpios,
            'features_por_cuenta' => $agregadosPorCuenta,
        ];

        if (!is_dir(dirname($rutaSalida))) {
            mkdir(dirname($rutaSalida), 0755, true);
        }

        file_put_contents($rutaSalida, json_encode($resultado, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->info("Salida escrita en: {$rutaSalida}");

        return 0;
    }

    private function limpiarRegistro(array $registro): ?array
    {
        // Monto: si viene vacío, se descarta el registro (no podemos inferir un monto)
        $montoCrudo = trim($registro['monto'] ?? '');
        if ($montoCrudo === '') {
            return null;
        }

        // Estandarizar monto: quitar comas de miles, castear a float
        $monto = (float) str_replace(',', '', $montoCrudo);

        // Estandarizar fecha: soporta YYYY-MM-DD, MM/DD/YYYY, DD-MM-YYYY, YYYY/MM/DD
        $fecha = $this->normalizarFecha($registro['fecha'] ?? '');
        if ($fecha === null) {
            return null;
        }

        return [
            'cuenta_id' => $registro['cuenta_id'] ?? null,
            'fecha' => $fecha,
            'monto' => round($monto, 2),
            'moneda' => strtoupper(trim($registro['moneda'] ?? 'USD')),
            'referencia' => trim($registro['referencia'] ?? '') ?: null,
        ];
    }

    private function normalizarFecha(string $fechaCruda): ?string
    {
        $formatos = ['Y-m-d', 'm/d/Y', 'd-m-Y', 'Y/m/d'];

        foreach ($formatos as $formato) {
            try {
                $fecha = Carbon::createFromFormat($formato, trim($fechaCruda));
                if ($fecha !== false) {
                    return $fecha->toDateString();
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    private function agregarPorCuenta(array $registros): array
    {
        $porCuenta = [];

        foreach ($registros as $registro) {
            $cuentaId = $registro['cuenta_id'];

            if (!isset($porCuenta[$cuentaId])) {
                $porCuenta[$cuentaId] = [
                    'cuenta_id' => $cuentaId,
                    'total_transacciones' => 0,
                    'monto_total' => 0,
                    'monto_promedio' => 0,
                    'ultima_transaccion' => null,
                ];
            }

            $porCuenta[$cuentaId]['total_transacciones']++;
            $porCuenta[$cuentaId]['monto_total'] += $registro['monto'];

            if ($porCuenta[$cuentaId]['ultima_transaccion'] === null
                || $registro['fecha'] > $porCuenta[$cuentaId]['ultima_transaccion']) {
                $porCuenta[$cuentaId]['ultima_transaccion'] = $registro['fecha'];
            }
        }

        foreach ($porCuenta as &$datos) {
            $datos['monto_promedio'] = round($datos['monto_total'] / $datos['total_transacciones'], 2);
            $datos['monto_total'] = round($datos['monto_total'], 2);
        }

        return array_values($porCuenta);
    }
}