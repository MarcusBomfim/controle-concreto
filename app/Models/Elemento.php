<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de persistência do elemento estrutural.
 *
 * A chave é composta — (obra_codigo, codigo) —, e o Eloquent não tem suporte
 * a chave composta. Para ler e listar isso não importa: as consultas são por
 * `where`. Para gravar, o repositório usa `upsert()`, que também não depende
 * da chave primária do model.
 */
final class Elemento extends Model
{
    protected $table = 'elementos';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'fck' => 'integer',
            'abatimento_mm' => 'integer',
            'volume_previsto_m3' => 'float',
        ];
    }
}
