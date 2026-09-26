<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * O dia de concretagem: a concretagem, os caminhões, os exemplares e os
 * cilindros.
 *
 * As chaves primárias compostas são a própria regra de negócio. Em
 * `exemplares`, (obra, concretagem, carga, idade) significa "um exemplar
 * por carga e idade" — não é possível moldar dois exemplares de 28 dias da
 * mesma carga, e isso não depende de nenhuma verificação em PHP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('concretagens', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('numero');
            $tabela->string('elemento_codigo', 30);
            $tabela->date('data');
            $tabela->string('fornecedor', 120);
            $tabela->string('responsavel', 160);
            $tabela->enum('situacao', ['em_andamento', 'concluida', 'cancelada']);
            $tabela->timestamp('criado_em')->useCurrent();

            $tabela->primary(['obra_codigo', 'numero']);

            /*
             * CASCADE, e não RESTRICT. A regra desejável seria "não apague
             * elemento já concretado" — mas apagar a obra cascateia para os
             * elementos, e um RESTRICT aqui bloquearia a exclusão da obra
             * inteira. Proteger o elemento concretado é política, e fica na
             * aplicação, onde dá para explicar o motivo a quem tentou.
             */
            $tabela->foreign(['obra_codigo', 'elemento_codigo'])
                ->references(['obra_codigo', 'codigo'])->on('elementos')
                ->cascadeOnDelete()->cascadeOnUpdate();

            $tabela->index(['obra_codigo', 'elemento_codigo', 'data'], 'idx_concretagens_elemento');
        });

        // Cada caminhão, aceito ou devolvido. A devolvida fica: é a prova
        // na discussão com a usina sobre quem paga o concreto recusado.
        Schema::create('cargas', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('concretagem_numero');
            $tabela->unsignedInteger('numero');
            $tabela->string('nota_fiscal', 40);
            $tabela->string('placa', 10)->nullable();
            $tabela->float('volume_m3');
            $tabela->dateTime('saida_da_usina');
            $tabela->dateTime('chegada');
            $tabela->unsignedSmallInteger('abatimento_mm');

            $tabela->enum('devolucao', [
                'abatimento_fora_da_faixa',
                'tempo_de_transporte_excedido',
            ])->nullable();

            $tabela->string('observacao', 300)->nullable();

            $tabela->primary(['obra_codigo', 'concretagem_numero', 'numero']);

            $tabela->foreign(['obra_codigo', 'concretagem_numero'])
                ->references(['obra_codigo', 'numero'])->on('concretagens')
                ->cascadeOnDelete();
        });

        // Um exemplar por carga e idade: a chave primária é a própria regra.
        Schema::create('exemplares', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('concretagem_numero');
            $tabela->unsignedInteger('carga_numero');
            $tabela->unsignedSmallInteger('idade_dias');
            $tabela->dateTime('moldado_em');

            $tabela->primary(['obra_codigo', 'concretagem_numero', 'carga_numero', 'idade_dias']);

            $tabela->foreign(['obra_codigo', 'concretagem_numero', 'carga_numero'])
                ->references(['obra_codigo', 'concretagem_numero', 'numero'])->on('cargas')
                ->cascadeOnDelete();
        });

        /*
         * Os cilindros. A tabela mais consultada do sistema.
         *
         * A pergunta que o laboratório faz todo dia é "o que rompe hoje?", e
         * ela precisa responder rápido mesmo com milhares de corpos de prova
         * em cura. Por isso a janela de rompimento está gravada em colunas, e
         * não calculada na consulta: rompimento_previsto, inicio_janela e
         * fim_janela derivam de moldado_em e da idade, mas ficam aqui para o
         * índice existir.
         *
         * É desnormalização deliberada. O custo seria manter os três
         * coerentes com a moldagem — e como o corpo de prova é imutável
         * depois de moldado, o custo é zero.
         */
        Schema::create('corpos_de_prova', function (Blueprint $tabela): void {
            $tabela->string('obra_codigo', 20);
            $tabela->unsignedInteger('concretagem_numero');
            $tabela->unsignedInteger('carga_numero');
            $tabela->unsignedSmallInteger('idade_dias');
            $tabela->enum('letra', ['A', 'B']);

            $tabela->string('identificacao', 40);
            $tabela->dateTime('moldado_em');
            $tabela->dateTime('rompimento_previsto');
            $tabela->dateTime('inicio_janela');
            $tabela->dateTime('fim_janela');

            $tabela->enum('situacao', ['curando', 'rompido', 'descartado'])->default('curando');

            $tabela->primary(['obra_codigo', 'concretagem_numero', 'carga_numero', 'idade_dias', 'letra']);

            $tabela->foreign(['obra_codigo', 'concretagem_numero', 'carga_numero', 'idade_dias'])
                ->references(['obra_codigo', 'concretagem_numero', 'carga_numero', 'idade_dias'])
                ->on('exemplares')
                ->cascadeOnDelete();

            // "O que rompe hoje": filtra por situação e varre um intervalo.
            $tabela->index(['situacao', 'rompimento_previsto'], 'idx_cp_agenda');

            // "O que venceu": ainda curando e com a janela já fechada.
            $tabela->index(['situacao', 'fim_janela'], 'idx_cp_vencidos');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corpos_de_prova');
        Schema::dropIfExists('exemplares');
        Schema::dropIfExists('cargas');
        Schema::dropIfExists('concretagens');
    }
};
