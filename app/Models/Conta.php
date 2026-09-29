<?php

declare(strict_types=1);

namespace App\Models;

use App\Dominio\Usuario\Papel;
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as PodeAutenticar;
use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de persistência da conta de acesso — e a identidade que o guard do
 * Laravel carrega na sessão.
 *
 * É o único model que faz dois papéis, e por um motivo: o `SessionGuard`
 * exige um `Authenticatable`, e não há como entregar a ele a entidade
 * `Usuario` do domínio sem arrastar o framework para dentro do domínio. A
 * divisão que sobra é limpa: o `Usuario` tem as regras de criação de conta —
 * e-mail válido, senha de 8 caracteres —, e a `Conta` é quem o guard conhece.
 *
 * As três sobrescritas abaixo existem porque a tabela é nossa, não do
 * Laravel: a chave é `email` e a coluna da senha é `hash_senha`.
 *
 * O `hidden` não protege o banco — protege o descuido: se algum dia este
 * model for serializado numa resposta JSON, o hash da senha não vai junto.
 */
final class Conta extends Model implements PodeAutenticar
{
    use Authenticatable;

    protected $table = 'contas';

    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['hash_senha'];

    /**
     * A coluna da senha não se chama `password`.
     *
     * Sobrescrito como método, e não como propriedade: a trait
     * `Authenticatable` já declara `$authPasswordName` com valor, e
     * redeclarar uma propriedade de trait com outro valor é erro fatal de
     * composição em PHP.
     */
    public function getAuthPasswordName(): string
    {
        return 'hash_senha';
    }

    /** Sem "lembrar de mim": a tabela não tem a coluna, e vazio desliga o recurso. */
    public function getRememberTokenName(): string
    {
        return '';
    }

    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',

            // O papel chega como enum do domínio, e é assim que os Gates o leem.
            'papel' => Papel::class,
        ];
    }
}
