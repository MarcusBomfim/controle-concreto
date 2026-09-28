<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dominio\Ensaio\DiametroDoCorpoDeProva;
use DateTimeImmutable;
use Illuminate\Validation\Rule;

/**
 * O que a prensa mediu.
 *
 * A força vem em kN e a resistência sai em MPa pela área do cilindro — por
 * isso o diâmetro é enum e não número livre: confundir 10 com 15 cm erra o
 * resultado em 2,25 vezes, e um erro desses passa despercebido num laudo.
 */
final class RomperRequest extends RequisicaoDeCorpoDeProva
{
    /** @return array<string, list<mixed>> */
    protected function regras(): array
    {
        return [
            'carga_kn' => ['required', 'numeric', 'gt:0'],
            'diametro_mm' => ['required', 'integer', Rule::enum(DiametroDoCorpoDeProva::class)],
            'rompido_em' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'carga_kn' => $this->has('carga_kn')
                ? str_replace(',', '.', (string) $this->input('carga_kn'))
                : null,
            'diametro_mm' => is_scalar($this->input('diametro_mm'))
                ? (int) $this->input('diametro_mm')
                : null,
        ], static fn (mixed $valor): bool => $valor !== null));
    }

    public function cargaEmKN(): float
    {
        return (float) $this->validated('carga_kn');
    }

    public function diametroEmMm(): int
    {
        return (int) $this->validated('diametro_mm');
    }

    public function rompidoEm(): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $this->validated('rompido_em'));
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'carga_kn' => 'carga de ruptura',
            'diametro_mm' => 'diâmetro do corpo de prova',
            'rompido_em' => 'data e hora do rompimento',
        ];
    }
}
