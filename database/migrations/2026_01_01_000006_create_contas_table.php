<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Contas de acesso do controle tecnológico.
 *
 * A tabela se chama `contas`, e não `usuarios`, para não colidir com a
 * `users` que o Laravel cria por padrão: são coisas diferentes, e misturar
 * as duas confundiria quem chegasse depois.
 *
 * O e-mail é a chave e é guardado em minúsculas pela entidade, então a busca
 * não precisa de LOWER() nem de índice funcional.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('contas', function (Blueprint $tabela): void {
            $tabela->string('email', 160)->primary();
            $tabela->string('nome', 120);
            $tabela->enum('papel', ['engenheiro', 'laboratorista', 'gestor']);

            // Só o hash. A senha em texto não passa pelo banco em momento nenhum.
            $tabela->string('hash_senha', 255);

            $tabela->boolean('ativo')->default(true);

            $tabela->timestamp('criado_em')->useCurrent();
            $tabela->timestamp('atualizado_em')->useCurrent();

            $tabela->index(['papel', 'ativo'], 'idx_contas_papel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contas');
    }
};
