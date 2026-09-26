<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * O tratamento do lote reprovado.
 *
 * Uma não conformidade por lote: a chave primária é a mesma do lote, e a
 * chave estrangeira garante que ela só existe para lote que existe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nao_conformidades', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('lote_numero');

            $tabela->dateTime('aberta_em');
            $tabela->float('fck_projeto_mpa');
            $tabela->float('fck_estimado_mpa');

            $tabela->enum('situacao', ['aberta', 'encerrada'])->default('aberta');

            // Preenchidos no encerramento, os três juntos.
            $tabela->enum('desfecho', ['estrutura_aceita', 'reforcada', 'demolida'])->nullable();
            $tabela->text('parecer')->nullable();
            $tabela->dateTime('encerrada_em')->nullable();

            $tabela->primary(['obra_codigo', 'lote_numero']);

            $tabela->foreign(['obra_codigo', 'lote_numero'])
                ->references(['obra_codigo', 'numero'])->on('lotes')
                ->cascadeOnDelete()->cascadeOnUpdate();

            $tabela->index(['situacao', 'aberta_em'], 'idx_nc_abertas');
        });

        foreach (['INSERT', 'UPDATE'] as $evento) {
            $nome = 'nc_coerencia_' . strtolower($evento);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$nome}
                BEFORE {$evento} ON nao_conformidades
                WHEN NEW.fck_estimado_mpa >= NEW.fck_projeto_mpa
                  OR (NEW.situacao = 'aberta'
                      AND (NEW.desfecho IS NOT NULL OR NEW.parecer IS NOT NULL
                           OR NEW.encerrada_em IS NOT NULL))
                  OR (NEW.situacao = 'encerrada'
                      AND (NEW.desfecho IS NULL OR NEW.parecer IS NULL
                           OR NEW.encerrada_em IS NULL))
                BEGIN
                    SELECT RAISE(ABORT, 'nao conformidade so existe para lote abaixo do fck, e encerrada exige desfecho, parecer e data');
                END;
            SQL);
        }

        /*
         * Os passos do tratamento, numerados na ordem em que foram
         * registrados. Providência não se edita nem se apaga: o histórico
         * é a prova.
         */
        Schema::create('providencias', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('lote_numero');
            $tabela->unsignedInteger('numero');

            $tabela->enum('tipo', [
                'revisao_de_projeto', 'ensaio_nao_destrutivo', 'extracao_de_testemunhos',
                'prova_de_carga', 'reforco', 'demolicao',
            ]);

            $tabela->dateTime('realizada_em');
            $tabela->text('descricao');
            $tabela->enum('resultado', ['favoravel', 'desfavoravel', 'informativo']);
            $tabela->string('responsavel', 160);

            // Só a extração de testemunhos devolve um fck medido na peça.
            $tabela->float('fck_obtido_mpa')->nullable();

            $tabela->primary(['obra_codigo', 'lote_numero', 'numero']);

            $tabela->foreign(['obra_codigo', 'lote_numero'])
                ->references(['obra_codigo', 'lote_numero'])->on('nao_conformidades')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        foreach (['INSERT', 'UPDATE'] as $evento) {
            $nome = 'providencia_fck_so_em_testemunho_' . strtolower($evento);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$nome}
                BEFORE {$evento} ON providencias
                WHEN (NEW.tipo = 'extracao_de_testemunhos' AND NEW.fck_obtido_mpa IS NULL)
                  OR (NEW.tipo <> 'extracao_de_testemunhos' AND NEW.fck_obtido_mpa IS NOT NULL)
                BEGIN
                    SELECT RAISE(ABORT, 'o fck obtido so se informa na extracao de testemunhos, e nela e obrigatorio');
                END;
            SQL);
        }
    }

    public function down(): void
    {
        foreach (['insert', 'update'] as $evento) {
            DB::unprepared("DROP TRIGGER IF EXISTS providencia_fck_so_em_testemunho_{$evento}");
            DB::unprepared("DROP TRIGGER IF EXISTS nc_coerencia_{$evento}");
        }

        Schema::dropIfExists('providencias');
        Schema::dropIfExists('nao_conformidades');
    }
};
