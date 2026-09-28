<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * O motivo é obrigatório: um cilindro que some sem explicação é
 * exatamente o que uma auditoria procura.
 */
final class DescartarRequest extends RequisicaoDeCorpoDeProva
{
    /** @return array<string, list<mixed>> */
    protected function regras(): array
    {
        return ['motivo' => ['required', 'string', 'max:300']];
    }

    public function motivo(): string
    {
        return (string) $this->validated('motivo');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['motivo' => 'motivo do descarte'];
    }
}
