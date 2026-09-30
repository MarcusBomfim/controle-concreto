<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * O que a prensa mediu, e os gatilhos que mantêm situação e resultado
 * coerentes.
 *
 * Aqui o Schema Builder chega ao limite: ele não expõe CHECK, e o SQLite não
 * aceita adicionar um CHECK depois que a tabela existe. As regras que
 * dependem de mais de uma coluna vão em gatilho — que o SQLite aceita criar
 * separado. É a mesma garantia que o domínio já dá, repetida no banco para
 * que nenhum caminho escape.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('corpos_de_prova', function (Blueprint $tabela): void {
            $tabela->dateTime('rompido_em')->nullable();
            $tabela->float('carga_kn')->nullable();
            $tabela->unsignedSmallInteger('diametro_mm')->nullable();

            /*
             * A resistência é derivada de carga_kn e diametro_mm, mas fica
             * gravada: a conta do lote varre resistências aos milhares, e
             * recalcular força/área linha a linha no SQL é o tipo de coisa
             * que funciona no teste e arrasta em produção.
             */
            $tabela->float('resistencia_mpa')->nullable();

            $tabela->string('motivo_descarte', 300)->nullable();
        });

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cp_rompido_exige_resultado
            BEFORE UPDATE OF situacao ON corpos_de_prova
            WHEN NEW.situacao = 'rompido'
             AND (NEW.rompido_em IS NULL OR NEW.carga_kn IS NULL
                  OR NEW.diametro_mm IS NULL OR NEW.resistencia_mpa IS NULL)
            BEGIN
                SELECT RAISE(ABORT, 'corpo de prova rompido exige data, carga, diametro e resistencia');
            END;
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cp_descartado_exige_motivo
            BEFORE UPDATE OF situacao ON corpos_de_prova
            WHEN NEW.situacao = 'descartado'
             AND (NEW.motivo_descarte IS NULL OR NEW.motivo_descarte = '')
            BEGIN
                SELECT RAISE(ABORT, 'corpo de prova descartado exige motivo');
            END;
        SQL);

        // Rompido ou descartado é final: a situação não volta para curando.
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER cp_situacao_nao_regride
            BEFORE UPDATE OF situacao ON corpos_de_prova
            WHEN OLD.situacao <> 'curando' AND NEW.situacao <> OLD.situacao
            BEGIN
                SELECT RAISE(ABORT, 'corpo de prova rompido ou descartado nao muda de situacao');
            END;
        SQL);
    }

    public function down(): void
    {
        foreach (['cp_situacao_nao_regride', 'cp_descartado_exige_motivo', 'cp_rompido_exige_resultado'] as $gatilho) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$gatilho}");
        }

        Schema::table('corpos_de_prova', function (Blueprint $tabela): void {
            $tabela->dropColumn([
                'rompido_em', 'carga_kn', 'diametro_mm', 'resistencia_mpa', 'motivo_descarte',
            ]);
        });
    }
};
