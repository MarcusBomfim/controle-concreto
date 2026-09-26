<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * O lote de aceitação da NBR 12655 e as concretagens que o compõem.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lotes', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('numero');

            $tabela->unsignedSmallInteger('fck');
            $tabela->enum('grupo', ['vertical', 'horizontal']);
            $tabela->enum('condicao', ['a', 'b', 'c']);
            $tabela->enum('amostragem', ['parcial', 'total']);
            $tabela->enum('situacao', ['aberto', 'aceito', 'nao_conforme'])->default('aberto');

            // Preenchidos no julgamento. A memória de cálculo vai em JSON:
            // é o registro de como se chegou ao número, e precisa sobreviver
            // a qualquer mudança futura na calculadora.
            $tabela->float('fck_estimado_mpa')->nullable();
            $tabela->dateTime('julgado_em')->nullable();
            $tabela->json('memoria_de_calculo')->nullable();

            $tabela->timestamp('criado_em')->useCurrent();

            $tabela->primary(['obra_codigo', 'numero']);

            $tabela->foreign('obra_codigo')
                ->references('codigo')->on('obras')
                ->cascadeOnDelete()->cascadeOnUpdate();
        });

        // Julgado tem número e data; aberto não tem. O CHECK original não
        // cabe no Schema Builder, então a coerência vai em gatilho.
        foreach (['INSERT', 'UPDATE'] as $evento) {
            $nome = 'lote_julgado_exige_estimativa_' . strtolower($evento);

            DB::unprepared(<<<SQL
                CREATE TRIGGER {$nome}
                BEFORE {$evento} ON lotes
                WHEN (NEW.situacao = 'aberto'
                      AND (NEW.fck_estimado_mpa IS NOT NULL OR NEW.julgado_em IS NOT NULL))
                  OR (NEW.situacao <> 'aberto'
                      AND (NEW.fck_estimado_mpa IS NULL OR NEW.julgado_em IS NULL))
                BEGIN
                    SELECT RAISE(ABORT, 'lote julgado exige fck estimado e data; lote aberto nao os tem');
                END;
            SQL);
        }

        /*
         * Quais concretagens compõem cada lote.
         *
         * A chave primária é (obra, concretagem) — e não (obra, lote,
         * concretagem) — de propósito: uma concretagem entra em UM lote só.
         * Duas pessoas formando lotes ao mesmo tempo com a mesma concretagem
         * passariam por qualquer verificação em PHP; a chave primária não
         * deixa a segunda gravar.
         */
        Schema::create('lote_concretagens', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('lote_numero');
            $tabela->unsignedInteger('concretagem_numero');

            $tabela->primary(['obra_codigo', 'concretagem_numero']);

            $tabela->foreign(['obra_codigo', 'lote_numero'])
                ->references(['obra_codigo', 'numero'])->on('lotes')
                ->cascadeOnDelete();

            $tabela->foreign(['obra_codigo', 'concretagem_numero'])
                ->references(['obra_codigo', 'numero'])->on('concretagens')
                ->cascadeOnDelete();

            $tabela->index(['obra_codigo', 'lote_numero'], 'idx_lote_concretagens_lote');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lote_concretagens');

        foreach (['insert', 'update'] as $evento) {
            DB::unprepared("DROP TRIGGER IF EXISTS lote_julgado_exige_estimativa_{$evento}");
        }

        Schema::dropIfExists('lotes');
    }
};
