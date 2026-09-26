<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo de persistência da obra.
 *
 * Não é a entidade: é o registro da tabela. Quem tem as regras é
 * App\Dominio\Obra\Obra, e o repositório traduz de um para o outro. Um
 * model do Eloquent é Active Record — sabe se gravar — e misturar isso com
 * as invariantes do domínio daria uma classe que valida e persiste ao mesmo
 * tempo. Separar custa uma tradução e mantém o domínio sem saber que banco
 * existe.
 *
 * Por isso este model não tem método nenhum: ele é configuração.
 */
final class Obra extends Model
{
    protected $table = 'obras';

    protected $primaryKey = 'codigo';

    // A chave é o código da obra ("OBR-2026-007"), não um id que cresce.
    public $incrementing = false;

    protected $keyType = 'string';

    // A tabela tem criado_em, mas não updated_at: o Eloquent não gerencia.
    public $timestamps = false;

    protected $guarded = [];
}
