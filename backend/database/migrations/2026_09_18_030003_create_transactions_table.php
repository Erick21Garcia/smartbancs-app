<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key')->unique();
            $table->foreignUuid('cuenta_origen_id')->constrained('accounts');
            $table->foreignUuid('cuenta_destino_id')->nullable()->constrained('accounts');
            $table->string('referencia_externa')->nullable();
            $table->decimal('monto', 15, 2);
            $table->string('moneda', 3)->default('USD');
            $table->enum('estado', ['pending', 'completed', 'failed', 'compensating'])->default('pending');
            $table->string('correlation_id')->index();
            $table->text('motivo_fallo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
