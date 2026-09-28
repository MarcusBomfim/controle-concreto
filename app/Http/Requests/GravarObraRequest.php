<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de formato, antes de o domínio ver os dados.
 *
 * Isto **não** substitui as regras da entidade Obra: ela continua recusando
 * campo em branco e registro fora de formato, e é ela quem garante isso em
 * qualquer caminho — seeder, comando de console, teste. O Form Request
 * existe para outra coisa: devolver ao formulário uma lista de erros por
 * campo, em vez da primeira exceção que o domínio lançar.
 *
 * Duas camadas com propósitos diferentes, não duplicação: aqui se checa
 * formato, lá se checa invariante.
 */
final class GravarObraRequest extends FormRequest
{
    /** A autorização por papel entra na Parte 6. */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'codigo' => ['required', 'string', 'max:20'],
            'nome' => ['required', 'string', 'max:160'],
            'cliente' => ['required', 'string', 'max:160'],
            'responsavel_tecnico' => ['required', 'string', 'max:160'],
            'registro_profissional' => ['required', 'string', 'max:30'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'codigo' => 'código da obra',
            'responsavel_tecnico' => 'responsável técnico',
            'registro_profissional' => 'registro profissional',
        ];
    }
}
