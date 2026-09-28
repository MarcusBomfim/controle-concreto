<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\TipoDeElemento;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GravarElementoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:30'],

            // Rule::enum aceita só os valores do enum do domínio: a lista
            // de tipos válidos não é repetida aqui.
            'tipo' => ['required', Rule::enum(TipoDeElemento::class)],

            'descricao' => ['required', 'string', 'max:200'],
            'pavimento' => ['nullable', 'string', 'max:60'],
            'fck' => ['required', Rule::enum(ClasseDeResistencia::class)],
            'abatimento_mm' => ['required', 'integer', 'between:10,250'],
            'volume_previsto_m3' => ['required', 'string'],
        ];
    }

    /**
     * O formulário em português manda "42,0"; o PHP quer ponto.
     *
     * prepareForValidation roda antes das regras, então a conversão fica em
     * um lugar só — e não espalhada pelo controlador.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('volume_previsto_m3')) {
            $this->merge([
                'volume_previsto_m3' => str_replace(',', '.', (string) $this->input('volume_previsto_m3')),
            ]);
        }
    }

    public function volumeEmM3(): float
    {
        return (float) $this->validated('volume_previsto_m3');
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'codigo' => 'código do elemento',
            'fck' => 'classe de resistência',
            'abatimento_mm' => 'abatimento',
            'volume_previsto_m3' => 'volume previsto',
        ];
    }
}
