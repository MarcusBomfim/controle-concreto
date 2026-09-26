<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de persistência da conta de acesso.
 *
 * O `hidden` não protege o banco — protege o descuido: se algum dia este
 * model for serializado numa resposta JSON, o hash da senha não vai junto.
 */
final class Conta extends Model
{
    protected $table = 'contas';

    protected $primaryKey = 'email';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $guarded = [];

    protected $hidden = ['hash_senha'];

    protected function casts(): array
    {
        return ['ativo' => 'boolean'];
    }
}
