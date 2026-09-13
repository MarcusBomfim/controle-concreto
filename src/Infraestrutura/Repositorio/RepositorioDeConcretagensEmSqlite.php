<?php

declare(strict_types=1);

namespace ControleConcreto\Infraestrutura\Repositorio;

use DateTimeImmutable;
use ControleConcreto\Dominio\Concretagem\Carga;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concretagem\MotivoDeDevolucao;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Concretagem\SituacaoDaConcretagem;
use ControleConcreto\Dominio\Ensaio\CorpoDeProva;
use ControleConcreto\Dominio\Ensaio\Exemplar;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use PDO;
use Throwable;

/**
 * Grava e recompõe o agregado inteiro: concretagem, cargas, exemplares e
 * corpos de prova.
 *
 * Cargas e exemplares são imutáveis depois de gravados, então o INSERT usa
 * ON CONFLICT DO NOTHING — salvar de novo só acrescenta o que é novo. O
 * corpo de prova é a exceção: a situação dele muda quando é rompido.
 */
final class RepositorioDeConcretagensEmSqlite implements RepositorioDeConcretagens
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(Concretagem $concretagem): int
    {
        return $this->emTransacao(function () use ($concretagem): int {
            $numero = $concretagem->numero() > 0
                ? $concretagem->numero()
                : $this->proximoNumero($concretagem->obraCodigo);

            $comando = $this->conexao->prepare(
                'INSERT INTO concretagens
                    (obra_codigo, numero, elemento_codigo, data, fornecedor, responsavel, situacao)
                 VALUES (:obra_codigo, :numero, :elemento_codigo, :data, :fornecedor, :responsavel, :situacao)
                 ON CONFLICT (obra_codigo, numero) DO UPDATE SET
                    situacao = excluded.situacao'
            );

            $comando->execute([
                ':obra_codigo' => $concretagem->obraCodigo,
                ':numero' => $numero,
                ':elemento_codigo' => $concretagem->elemento->codigo,
                ':data' => $concretagem->data->format('Y-m-d'),
                ':fornecedor' => $concretagem->fornecedor,
                ':responsavel' => $concretagem->responsavel,
                ':situacao' => $concretagem->situacao()->value,
            ]);

            $this->gravarCargas($concretagem, $numero);
            $this->gravarExemplares($concretagem, $numero);

            return $numero;
        });
    }

    public function porNumero(string $obraCodigo, int $numero): ?Concretagem
    {
        $consulta = $this->conexao->prepare(
            self::selecao() . ' WHERE c.obra_codigo = :obra_codigo AND c.numero = :numero'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':numero' => $numero,
        ]);

        return $this->montarVarias($consulta->fetchAll())[0] ?? null;
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            self::selecao() . ' WHERE c.obra_codigo = :obra_codigo ORDER BY c.data DESC, c.numero DESC'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        return $this->montarVarias($consulta->fetchAll());
    }

    public function doElemento(string $obraCodigo, string $elementoCodigo): array
    {
        $consulta = $this->conexao->prepare(
            self::selecao() . ' WHERE c.obra_codigo = :obra_codigo AND c.elemento_codigo = :elemento
             ORDER BY c.data, c.numero'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':elemento' => self::normalizar($elementoCodigo),
        ]);

        return $this->montarVarias($consulta->fetchAll());
    }

    /** A concretagem sempre vem com o elemento: é dele a especificação que ela julga. */
    private static function selecao(): string
    {
        return 'SELECT c.obra_codigo, c.numero, c.data, c.fornecedor, c.responsavel, c.situacao,
                       e.codigo, e.tipo, e.descricao, e.pavimento, e.fck, e.abatimento_mm,
                       e.volume_previsto_m3
                FROM concretagens c
                JOIN elementos e ON e.obra_codigo = c.obra_codigo AND e.codigo = c.elemento_codigo';
    }

    private function proximoNumero(string $obraCodigo): int
    {
        $consulta = $this->conexao->prepare(
            'SELECT COALESCE(MAX(numero), 0) + 1 FROM concretagens WHERE obra_codigo = :obra_codigo'
        );
        $consulta->execute([':obra_codigo' => $obraCodigo]);

        return (int) $consulta->fetchColumn();
    }

    private function gravarCargas(Concretagem $concretagem, int $numero): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO cargas
                (obra_codigo, concretagem_numero, numero, nota_fiscal, placa, volume_m3,
                 saida_da_usina, chegada, abatimento_mm, devolucao, observacao)
             VALUES (:obra_codigo, :concretagem_numero, :numero, :nota_fiscal, :placa, :volume_m3,
                     :saida_da_usina, :chegada, :abatimento_mm, :devolucao, :observacao)
             ON CONFLICT (obra_codigo, concretagem_numero, numero) DO NOTHING'
        );

        foreach ($concretagem->cargas() as $carga) {
            $comando->execute([
                ':obra_codigo' => $concretagem->obraCodigo,
                ':concretagem_numero' => $numero,
                ':numero' => $carga->numero,
                ':nota_fiscal' => $carga->notaFiscal,
                ':placa' => $carga->placa,
                ':volume_m3' => $carga->volumeEmM3,
                ':saida_da_usina' => $carga->saidaDaUsina->format(self::FORMATO),
                ':chegada' => $carga->chegada->format(self::FORMATO),
                ':abatimento_mm' => $carga->abatimentoMedidoEmMm,
                ':devolucao' => $carga->devolucao?->value,
                ':observacao' => $carga->observacao,
            ]);
        }
    }

    private function gravarExemplares(Concretagem $concretagem, int $numero): void
    {
        $exemplar = $this->conexao->prepare(
            'INSERT INTO exemplares
                (obra_codigo, concretagem_numero, carga_numero, idade_dias, moldado_em)
             VALUES (:obra_codigo, :concretagem_numero, :carga_numero, :idade_dias, :moldado_em)
             ON CONFLICT (obra_codigo, concretagem_numero, carga_numero, idade_dias) DO NOTHING'
        );

        $corpoDeProva = $this->conexao->prepare(
            'INSERT INTO corpos_de_prova
                (obra_codigo, concretagem_numero, carga_numero, idade_dias, letra, identificacao,
                 moldado_em, rompimento_previsto, inicio_janela, fim_janela, situacao)
             VALUES (:obra_codigo, :concretagem_numero, :carga_numero, :idade_dias, :letra, :identificacao,
                     :moldado_em, :rompimento_previsto, :inicio_janela, :fim_janela, :situacao)
             ON CONFLICT (obra_codigo, concretagem_numero, carga_numero, idade_dias, letra) DO UPDATE SET
                situacao = excluded.situacao'
        );

        foreach ($concretagem->exemplares() as $ex) {
            $exemplar->execute([
                ':obra_codigo' => $concretagem->obraCodigo,
                ':concretagem_numero' => $numero,
                ':carga_numero' => $ex->cargaNumero,
                ':idade_dias' => $ex->idade->dias(),
                ':moldado_em' => $ex->moldadoEm->format(self::FORMATO),
            ]);

            foreach ([['A', $ex->primeiro], ['B', $ex->segundo]] as [$letra, $cp]) {
                $corpoDeProva->execute([
                    ':obra_codigo' => $concretagem->obraCodigo,
                    ':concretagem_numero' => $numero,
                    ':carga_numero' => $ex->cargaNumero,
                    ':idade_dias' => $ex->idade->dias(),
                    ':letra' => $letra,
                    ':identificacao' => $cp->identificacao,
                    ':moldado_em' => $cp->moldadoEm->format(self::FORMATO),
                    // Colunas derivadas, gravadas para o índice da agenda existir.
                    ':rompimento_previsto' => $cp->rompimentoPrevisto()->format(self::FORMATO),
                    ':inicio_janela' => $cp->inicioDaJanela()->format(self::FORMATO),
                    ':fim_janela' => $cp->fimDaJanela()->format(self::FORMATO),
                    ':situacao' => $cp->situacao()->value,
                ]);
            }
        }
    }

    /**
     * Recompõe várias concretagens carregando os filhos em três consultas,
     * e não em três por concretagem.
     *
     * @param  array<int, array<string, mixed>> $linhas
     * @return Concretagem[]
     */
    private function montarVarias(array $linhas): array
    {
        if ($linhas === []) {
            return [];
        }

        $obraCodigo = (string) $linhas[0]['obra_codigo'];
        $numeros = array_map(static fn (array $l): int => (int) $l['numero'], $linhas);

        $cargas = $this->carregarCargas($obraCodigo, $numeros);
        $exemplares = $this->carregarExemplares($obraCodigo, $numeros);

        $concretagens = [];

        foreach ($linhas as $linha) {
            $numero = (int) $linha['numero'];

            $concretagem = Concretagem::reconstituir(
                $obraCodigo,
                $numero,
                RepositorioDeElementosEmSqlite::montar($linha),
                new DateTimeImmutable((string) $linha['data']),
                (string) $linha['fornecedor'],
                (string) $linha['responsavel'],
                SituacaoDaConcretagem::from((string) $linha['situacao']),
            );

            foreach ($cargas[$numero] ?? [] as $carga) {
                $concretagem->anexarCarga($carga);
            }

            foreach ($exemplares[$numero] ?? [] as $exemplar) {
                $concretagem->anexarExemplar($exemplar);
            }

            $concretagens[] = $concretagem;
        }

        return $concretagens;
    }

    /**
     * @param  int[] $numeros
     * @return array<int, Carga[]>
     */
    private function carregarCargas(string $obraCodigo, array $numeros): array
    {
        $linhas = $this->filhos(
            'SELECT concretagem_numero, numero, nota_fiscal, placa, volume_m3, saida_da_usina,
                    chegada, abatimento_mm, devolucao, observacao
             FROM cargas',
            $obraCodigo,
            $numeros,
            'ORDER BY numero',
        );

        $porConcretagem = [];

        foreach ($linhas as $l) {
            $devolucao = $l['devolucao'];
            $placa = $l['placa'];
            $observacao = $l['observacao'];

            $porConcretagem[(int) $l['concretagem_numero']][] = new Carga(
                (int) $l['numero'],
                (string) $l['nota_fiscal'],
                $placa === null ? null : (string) $placa,
                (float) $l['volume_m3'],
                new DateTimeImmutable((string) $l['saida_da_usina']),
                new DateTimeImmutable((string) $l['chegada']),
                (int) $l['abatimento_mm'],
                $devolucao === null ? null : MotivoDeDevolucao::from((string) $devolucao),
                $observacao === null ? null : (string) $observacao,
            );
        }

        return $porConcretagem;
    }

    /**
     * @param  int[] $numeros
     * @return array<int, Exemplar[]>
     */
    private function carregarExemplares(string $obraCodigo, array $numeros): array
    {
        $linhas = $this->filhos(
            'SELECT e.concretagem_numero, e.carga_numero, e.idade_dias, e.moldado_em,
                    cp.letra, cp.identificacao, cp.situacao
             FROM exemplares e
             JOIN corpos_de_prova cp
               ON cp.obra_codigo = e.obra_codigo
              AND cp.concretagem_numero = e.concretagem_numero
              AND cp.carga_numero = e.carga_numero
              AND cp.idade_dias = e.idade_dias',
            $obraCodigo,
            $numeros,
            'ORDER BY e.carga_numero, e.idade_dias, cp.letra',
            'e.',
        );

        // Duas linhas por exemplar (A e B): agrupa antes de montar.
        $agrupado = [];

        foreach ($linhas as $l) {
            $chave = sprintf('%d-%d-%d', $l['concretagem_numero'], $l['carga_numero'], $l['idade_dias']);
            $agrupado[$chave]['meta'] = $l;
            $agrupado[$chave]['cps'][(string) $l['letra']] = CorpoDeProva::reconstituir(
                (string) $l['identificacao'],
                new DateTimeImmutable((string) $l['moldado_em']),
                IdadeDeEnsaio::from((int) $l['idade_dias']),
                SituacaoDoCorpoDeProva::from((string) $l['situacao']),
            );
        }

        $porConcretagem = [];

        foreach ($agrupado as $grupo) {
            $meta = $grupo['meta'];

            $porConcretagem[(int) $meta['concretagem_numero']][] = Exemplar::reconstituir(
                (int) $meta['carga_numero'],
                IdadeDeEnsaio::from((int) $meta['idade_dias']),
                new DateTimeImmutable((string) $meta['moldado_em']),
                $grupo['cps']['A'],
                $grupo['cps']['B'],
            );
        }

        return $porConcretagem;
    }

    /**
     * @param  int[] $numeros
     * @return array<int, array<string, mixed>>
     */
    private function filhos(
        string $select,
        string $obraCodigo,
        array $numeros,
        string $ordem = '',
        string $prefixo = '',
    ): array {
        $marcadores = implode(', ', array_fill(0, count($numeros), '?'));

        $consulta = $this->conexao->prepare(
            "{$select} WHERE {$prefixo}obra_codigo = ? AND {$prefixo}concretagem_numero IN ({$marcadores}) {$ordem}"
        );
        $consulta->execute([$obraCodigo, ...$numeros]);

        return $consulta->fetchAll();
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
