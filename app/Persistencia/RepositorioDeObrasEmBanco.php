<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use App\Models\Obra as ObraRegistro;

/**
 * A interface é a mesma do domínio; só a implementação mudou.
 *
 * Na versão em PHP puro isto era um INSERT ... ON CONFLICT DO UPDATE escrito
 * à mão. Aqui é `upsert()`, que o Eloquent traduz para o dialeto do banco em
 * uso — a mesma operação, sem SQL na mão.
 *
 * O alias `ObraRegistro` existe porque o arquivo precisa das duas classes
 * chamadas Obra: a entidade do domínio e o registro da tabela.
 */
final class RepositorioDeObrasEmBanco implements RepositorioDeObras
{
    public function salvar(Obra $obra): void
    {
        ObraRegistro::query()->upsert(
            [[
                'codigo' => $obra->codigo,
                'nome' => $obra->nome,
                'cliente' => $obra->cliente,
                'responsavel_tecnico' => $obra->responsavelTecnico,
                'registro_profissional' => $obra->registroProfissional,
            ]],
            ['codigo'],
            ['nome', 'cliente', 'responsavel_tecnico', 'registro_profissional'],
        );
    }

    public function porCodigo(string $codigo): ?Obra
    {
        $registro = ObraRegistro::query()->find(self::normalizar($codigo));

        return $registro === null ? null : self::montar($registro);
    }

    public function todas(): array
    {
        return ObraRegistro::query()
            ->orderBy('codigo')
            ->get()
            ->map(self::montar(...))
            ->all();
    }

    public function existe(string $codigo): bool
    {
        return ObraRegistro::query()->whereKey(self::normalizar($codigo))->exists();
    }

    /** Do registro da tabela para a entidade do domínio. */
    private static function montar(ObraRegistro $registro): Obra
    {
        return new Obra(
            (string) $registro->codigo,
            (string) $registro->nome,
            (string) $registro->cliente,
            (string) $registro->responsavel_tecnico,
            (string) $registro->registro_profissional,
        );
    }

    private static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }
}
