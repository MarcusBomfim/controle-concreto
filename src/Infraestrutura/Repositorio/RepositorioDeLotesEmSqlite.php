<?php

declare(strict_types=1);

namespace ControleConcreto\Infraestrutura\Repositorio;

use DateTimeImmutable;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\EstimativaDeFck;
use ControleConcreto\Dominio\Lote\Lote;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\Lote\SituacaoDoLote;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;
use PDO;
use Throwable;

/**
 * O lote referencia concretagens inteiras — com cargas, exemplares e
 * resultados — então recompor um lote é recompor as concretagens dele. Por
 * isso este repositório recebe o de concretagens, em vez de repetir o SQL.
 */
final class RepositorioDeLotesEmSqlite implements RepositorioDeLotes
{
    private const COLUNAS = 'obra_codigo, numero, fck, grupo, condicao, amostragem, situacao,
        fck_estimado_mpa, julgado_em, memoria_de_calculo';

    public function __construct(
        private readonly PDO $conexao,
        private readonly RepositorioDeConcretagens $concretagens,
    ) {
    }

    public function salvar(Lote $lote): int
    {
        return $this->emTransacao(function () use ($lote): int {
            $numero = $lote->numero() > 0 ? $lote->numero() : $this->proximoNumero($lote->obraCodigo);

            $comando = $this->conexao->prepare(
                'INSERT INTO lotes (' . self::COLUNAS . ')
                 VALUES (:obra_codigo, :numero, :fck, :grupo, :condicao, :amostragem, :situacao,
                         :fck_estimado_mpa, :julgado_em, :memoria_de_calculo)
                 ON CONFLICT (obra_codigo, numero) DO UPDATE SET
                    situacao           = excluded.situacao,
                    fck_estimado_mpa   = excluded.fck_estimado_mpa,
                    julgado_em         = excluded.julgado_em,
                    memoria_de_calculo = excluded.memoria_de_calculo'
            );

            $estimativa = $lote->estimativa();

            $comando->execute([
                ':obra_codigo' => $lote->obraCodigo,
                ':numero' => $numero,
                ':fck' => $lote->classe->value,
                ':grupo' => $lote->grupo->value,
                ':condicao' => $lote->condicao->value,
                ':amostragem' => $lote->amostragem->value,
                ':situacao' => $lote->situacao()->value,
                ':fck_estimado_mpa' => $estimativa?->fckEstimadoEmMPa,
                ':julgado_em' => $lote->julgadoEm()?->format('Y-m-d H:i:s'),
                ':memoria_de_calculo' => $estimativa === null ? null : self::serializar($estimativa),
            ]);

            $vinculo = $this->conexao->prepare(
                'INSERT INTO lote_concretagens (obra_codigo, lote_numero, concretagem_numero)
                 VALUES (:obra_codigo, :lote_numero, :concretagem_numero)
                 ON CONFLICT (obra_codigo, concretagem_numero) DO NOTHING'
            );

            foreach ($lote->concretagens() as $concretagem) {
                $vinculo->execute([
                    ':obra_codigo' => $lote->obraCodigo,
                    ':lote_numero' => $numero,
                    ':concretagem_numero' => $concretagem->numero(),
                ]);
            }

            return $numero;
        });
    }

    public function porNumero(string $obraCodigo, int $numero): ?Lote
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM lotes WHERE obra_codigo = :obra_codigo AND numero = :numero'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo), ':numero' => $numero]);

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->montar($linha);
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM lotes WHERE obra_codigo = :obra_codigo ORDER BY numero DESC'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        return array_map($this->montar(...), $consulta->fetchAll());
    }

    public function loteDaConcretagem(string $obraCodigo, int $concretagemNumero): ?int
    {
        $consulta = $this->conexao->prepare(
            'SELECT lote_numero FROM lote_concretagens
             WHERE obra_codigo = :obra_codigo AND concretagem_numero = :concretagem_numero'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':concretagem_numero' => $concretagemNumero,
        ]);

        $valor = $consulta->fetchColumn();

        return $valor === false ? null : (int) $valor;
    }

    private function proximoNumero(string $obraCodigo): int
    {
        $consulta = $this->conexao->prepare(
            'SELECT COALESCE(MAX(numero), 0) + 1 FROM lotes WHERE obra_codigo = :obra_codigo'
        );
        $consulta->execute([':obra_codigo' => $obraCodigo]);

        return (int) $consulta->fetchColumn();
    }

    /** @param array<string, mixed> $l */
    private function montar(array $l): Lote
    {
        $obraCodigo = (string) $l['obra_codigo'];
        $numero = (int) $l['numero'];

        $lote = Lote::reconstituir(
            $obraCodigo,
            $numero,
            ClasseDeResistencia::from((int) $l['fck']),
            GrupoDeSolicitacao::from((string) $l['grupo']),
            CondicaoDePreparo::from((string) $l['condicao']),
            TipoDeAmostragem::from((string) $l['amostragem']),
            SituacaoDoLote::from((string) $l['situacao']),
            $l['memoria_de_calculo'] === null ? null : self::desserializar((string) $l['memoria_de_calculo']),
            $l['julgado_em'] === null ? null : new DateTimeImmutable((string) $l['julgado_em']),
        );

        $vinculos = $this->conexao->prepare(
            'SELECT concretagem_numero FROM lote_concretagens
             WHERE obra_codigo = :obra_codigo AND lote_numero = :lote_numero ORDER BY concretagem_numero'
        );
        $vinculos->execute([':obra_codigo' => $obraCodigo, ':lote_numero' => $numero]);

        foreach ($vinculos->fetchAll() as $vinculo) {
            $concretagem = $this->concretagens->porNumero($obraCodigo, (int) $vinculo['concretagem_numero']);

            if ($concretagem !== null) {
                $lote->anexarConcretagem($concretagem);
            }
        }

        return $lote;
    }

    private static function serializar(EstimativaDeFck $e): string
    {
        return json_encode([
            'fck_estimado' => $e->fckEstimadoEmMPa,
            'n' => $e->numeroDeExemplares,
            'valores' => $e->valoresOrdenados,
            'amostragem' => $e->amostragem->value,
            'condicao' => $e->condicao->value,
            'metodo' => $e->metodo,
            'formula' => $e->valorDaFormula,
            'psi6' => $e->psi6,
            'piso' => $e->pisoDoPsi6,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private static function desserializar(string $json): EstimativaDeFck
    {
        /** @var array<string, mixed> $d */
        $d = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        return new EstimativaDeFck(
            (float) $d['fck_estimado'],
            (int) $d['n'],
            array_map(floatval(...), (array) $d['valores']),
            TipoDeAmostragem::from((string) $d['amostragem']),
            CondicaoDePreparo::from((string) $d['condicao']),
            (string) $d['metodo'],
            $d['formula'] === null ? null : (float) $d['formula'],
            $d['psi6'] === null ? null : (float) $d['psi6'],
            $d['piso'] === null ? null : (float) $d['piso'],
        );
    }

    private function emTransacao(callable $acao): mixed
    {
        if ($this->conexao->inTransaction()) {
            return $acao();
        }

        $this->conexao->beginTransaction();

        try {
            $resultado = $acao();
            $this->conexao->commit();

            return $resultado;
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }

    private static function normalizar(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }
}
