<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Models\Elemento as ElementoRegistro;

final class RepositorioDeElementosEmBanco implements RepositorioDeElementos
{
    public function salvar(string $obraCodigo, ElementoEstrutural $elemento): void
    {
        ElementoRegistro::query()->upsert(
            [[
                'obra_codigo' => self::normalizar($obraCodigo),
                'codigo' => $elemento->codigo,
                'tipo' => $elemento->tipo->value,
                'descricao' => $elemento->descricao,
                'pavimento' => $elemento->pavimento,
                'fck' => $elemento->classe->value,
                'abatimento_mm' => $elemento->abatimento->especificadoEmMm,
                'volume_previsto_m3' => $elemento->volumePrevistoEmM3,
            ]],
            ['obra_codigo', 'codigo'],
            ['tipo', 'descricao', 'pavimento', 'fck', 'abatimento_mm', 'volume_previsto_m3'],
        );
    }

    public function porCodigo(string $obraCodigo, string $codigo): ?ElementoEstrutural
    {
        $registro = ElementoRegistro::query()
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->where('codigo', self::normalizar($codigo))
            ->first();

        return $registro === null ? null : self::montar($registro);
    }

    public function daObra(string $obraCodigo): array
    {
        return ElementoRegistro::query()
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->orderBy('codigo')
            ->get()
            ->map(self::montar(...))
            ->all();
    }

    /**
     * Também é usada pelo repositório de concretagens, que carrega o
     * elemento junto com a concretagem.
     */
    public static function montar(ElementoRegistro $registro): ElementoEstrutural
    {
        return new ElementoEstrutural(
            (string) $registro->codigo,
            TipoDeElemento::from((string) $registro->tipo),
            (string) $registro->descricao,
            $registro->pavimento === null ? null : (string) $registro->pavimento,
            ClasseDeResistencia::from((int) $registro->fck),
            new Abatimento((int) $registro->abatimento_mm),
            (float) $registro->volume_previsto_m3,
        );
    }

    private static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }
}
