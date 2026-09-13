<?php

declare(strict_types=1);

namespace ControleConcreto\Infraestrutura\Repositorio;

use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\RepositorioDeElementos;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use PDO;

final class RepositorioDeElementosEmSqlite implements RepositorioDeElementos
{
    private const COLUNAS = 'obra_codigo, codigo, tipo, descricao, pavimento, fck,
        abatimento_mm, volume_previsto_m3';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(string $obraCodigo, ElementoEstrutural $elemento): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO elementos (' . self::COLUNAS . ')
             VALUES (:obra_codigo, :codigo, :tipo, :descricao, :pavimento, :fck,
                     :abatimento_mm, :volume_previsto_m3)
             ON CONFLICT (obra_codigo, codigo) DO UPDATE SET
                tipo               = excluded.tipo,
                descricao          = excluded.descricao,
                pavimento          = excluded.pavimento,
                fck                = excluded.fck,
                abatimento_mm      = excluded.abatimento_mm,
                volume_previsto_m3 = excluded.volume_previsto_m3'
        );

        $comando->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':codigo' => $elemento->codigo,
            ':tipo' => $elemento->tipo->value,
            ':descricao' => $elemento->descricao,
            ':pavimento' => $elemento->pavimento,
            ':fck' => $elemento->classe->value,
            ':abatimento_mm' => $elemento->abatimento->especificadoEmMm,
            ':volume_previsto_m3' => $elemento->volumePrevistoEmM3,
        ]);
    }

    public function porCodigo(string $obraCodigo, string $codigo): ?ElementoEstrutural
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM elementos
             WHERE obra_codigo = :obra_codigo AND codigo = :codigo'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':codigo' => self::normalizar($codigo),
        ]);

        $linha = $consulta->fetch();

        return $linha === false ? null : self::montar($linha);
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM elementos
             WHERE obra_codigo = :obra_codigo ORDER BY codigo'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        return array_map(self::montar(...), $consulta->fetchAll());
    }

    /** @param array<string, mixed> $linha */
    public static function montar(array $linha): ElementoEstrutural
    {
        $pavimento = $linha['pavimento'];

        return new ElementoEstrutural(
            (string) $linha['codigo'],
            TipoDeElemento::from((string) $linha['tipo']),
            (string) $linha['descricao'],
            $pavimento === null ? null : (string) $pavimento,
            ClasseDeResistencia::from((int) $linha['fck']),
            new Abatimento((int) $linha['abatimento_mm']),
            (float) $linha['volume_previsto_m3'],
        );
    }

    private static function normalizar(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }
}
