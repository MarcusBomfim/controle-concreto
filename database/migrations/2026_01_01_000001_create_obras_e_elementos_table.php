<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A obra e as peças que serão concretadas.
 *
 * Sobre o enum(): no SQLite ele vira uma coluna de texto com um CHECK IN
 * embutido — a mesma garantia que o SQL original escrevia à mão, mas gerada
 * pelo Schema Builder. É o caso em que o framework não custa nada e lê
 * melhor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('obras', function (Blueprint $tabela): void {
            $tabela->string('codigo', 20)->primary();
            $tabela->string('nome', 160);
            $tabela->string('cliente', 160);
            $tabela->string('responsavel_tecnico', 160);
            $tabela->string('registro_profissional', 30);
            $tabela->timestamp('criado_em')->useCurrent();
        });

        Schema::create('elementos', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->string('codigo', 30);

            $tabela->enum('tipo', [
                'fundacao', 'pilar', 'viga', 'laje',
                'parede', 'reservatorio', 'piso', 'outro',
            ]);

            $tabela->string('descricao', 200);
            $tabela->string('pavimento', 60)->nullable();

            // fck em MPa. As classes são as da NBR 8953: acima de 60 só
            // existem 70, 80, 90 e 100.
            $tabela->unsignedSmallInteger('fck');

            $tabela->unsignedSmallInteger('abatimento_mm');
            $tabela->float('volume_previsto_m3');
            $tabela->timestamp('criado_em')->useCurrent();

            $tabela->primary(['obra_codigo', 'codigo']);

            $tabela->foreign('obra_codigo')
                ->references('codigo')->on('obras')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('elementos');
        Schema::dropIfExists('obras');
    }
};
