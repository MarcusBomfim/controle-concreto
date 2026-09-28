<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\TipoDeAmostragem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * As concretagens marcadas e como o concreto foi preparado e amostrado.
 *
 * A classe e o grupo do lote não são perguntados: saem da primeira
 * concretagem marcada. Pedir para digitar "C30" de novo só abriria espaço
 * para o engano que a regra do lote existe para impedir.
 */
final class FormarLoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'concretagens' => ['required', 'array', 'min:1'],
            'concretagens.*' => ['integer', 'min:1'],
            'condicao' => ['required', Rule::enum(CondicaoDePreparo::class)],
            'amostragem' => ['required', Rule::enum(TipoDeAmostragem::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $marcadas = $this->input('concretagens');

        if (is_array($marcadas)) {
            $this->merge([
                'concretagens' => array_map(
                    static fn (mixed $numero): int => (int) $numero,
                    array_values(array_filter($marcadas, 'is_scalar')),
                ),
            ]);
        }
    }

    /** @return int[] */
    public function numerosDeConcretagem(): array
    {
        return array_values(array_unique($this->validated('concretagens')));
    }

    public function condicao(): CondicaoDePreparo
    {
        return CondicaoDePreparo::from((string) $this->validated('condicao'));
    }

    public function amostragem(): TipoDeAmostragem
    {
        return TipoDeAmostragem::from((string) $this->validated('amostragem'));
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'concretagens' => 'concretagens do lote',
            'condicao' => 'condição de preparo',
            'amostragem' => 'tipo de amostragem',
        ];
    }
}
