<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O caminhão que chegou ao canteiro.
 *
 * Os horários vêm só como hora (07:55): a data é a da concretagem, e o
 * domínio recusa carga de outro dia de qualquer jeito. Na versão em PHP
 * puro a conferência do formato HH:MM era um preg_match dentro do
 * controlador, que lançava exceção de domínio por um erro de digitação —
 * aqui é `date_format:H:i`, e o erro volta preso ao campo.
 */
final class ReceberCargaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'nota_fiscal' => ['required', 'string', 'max:40'],
            'placa' => ['nullable', 'string', 'max:10'],
            'volume_m3' => ['required', 'numeric', 'gt:0'],
            'saida' => ['required', 'date_format:H:i'],
            'chegada' => ['required', 'date_format:H:i'],
            'abatimento_mm' => ['required', 'integer', 'between:0,300'],
            'observacao' => ['nullable', 'string', 'max:300'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('volume_m3')) {
            $this->merge(['volume_m3' => str_replace(',', '.', (string) $this->input('volume_m3'))]);
        }
    }

    public function volumeEmM3(): float
    {
        return (float) $this->validated('volume_m3');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nota_fiscal' => 'nota fiscal',
            'volume_m3' => 'volume',
            'saida' => 'hora de saída da usina',
            'chegada' => 'hora de chegada',
            'abatimento_mm' => 'abatimento medido',
        ];
    }
}
