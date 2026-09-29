<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * A tabela de sessões do Laravel.
 *
 * O esqueleto do framework criava aqui `users`, `password_reset_tokens` e
 * `sessions`. As duas primeiras saíram: a conta de acesso deste sistema é a
 * tabela `contas`, com e-mail como chave e papel de norma — nada a ver com
 * a `users` genérica —, e não há recuperação de senha por e-mail.
 *
 * `sessions` fica porque SESSION_DRIVER=database: é onde mora o login, e é
 * também onde o Laravel guarda a URL pretendida de quem foi barrado antes
 * de entrar.
 *
 * A coluna `user_id` é inteira e vem assim do esqueleto. Aqui a chave da
 * conta é o e-mail, então ela nunca é preenchida — o Laravel só a usa para
 * facilitar consultas administrativas, e a sessão funciona sem ela.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $tabela): void {
            $tabela->string('id')->primary();
            $tabela->foreignId('user_id')->nullable()->index();
            $tabela->string('ip_address', 45)->nullable();
            $tabela->text('user_agent')->nullable();
            $tabela->longText('payload');
            $tabela->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
