<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dominio\Ensaio\IdadeDeEnsaio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * As idades marcadas no formulário de moldagem.
 *
 * Cada idade vira um exemplar — dois cilindros moldados no mesmo ato.
 * A lista de idades válidas não é repetida aqui: `Rule::enum` aponta para
 * o enum do domínio, que é quem sabe que existem 1, 3, 7, 28, 63 e 91 dias.
 */
final class MoldarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'carga' => ['required', 'integer', 'min:1'],
            'hora' => ['required', 'date_format:H:i'],
            'idades' => ['required', 'array', 'min:1'],
            'idades.*' => ['integer', Rule::enum(IdadeDeEnsaio::class)],
        ];
    }

    /**
     * Caixa de seleção chega como texto ("28"), e o enum é de inteiros.
     * A conversão fica antes das regras para que `Rule::enum` receba o tipo
     * que ele espera, em vez de depender de coerção.
     */
    protected function prepareForValidation(): void
    {
        $idades = $this->input('idades');

        if (is_array($idades)) {
            $this->merge([
                'idades' => array_map(
                    static fn (mixed $dias): int => (int) $dias,
                    array_values(array_filter($idades, 'is_scalar')),
                ),
            ]);
        }
    }

    /** @return IdadeDeEnsaio[] */
    public function idades(): array
    {
        return array_map(
            static fn (int $dias): IdadeDeEnsaio => IdadeDeEnsaio::from($dias),
            $this->validated('idades'),
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'hora' => 'hora da moldagem',
            'idades' => 'idades de ensaio',
        ];
    }
}
