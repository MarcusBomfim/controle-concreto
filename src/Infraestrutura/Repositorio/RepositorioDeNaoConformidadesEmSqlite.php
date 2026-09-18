<?php

declare(strict_types=1);

namespace ControleConcreto\Infraestrutura\Repositorio;

use DateTimeImmutable;
use ControleConcreto\Dominio\NaoConformidade\Desfecho;
use ControleConcreto\Dominio\NaoConformidade\NaoConformidade;
use ControleConcreto\Dominio\NaoConformidade\Providencia;
use ControleConcreto\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use ControleConcreto\Dominio\NaoConformidade\ResultadoDaProvidencia;
use ControleConcreto\Dominio\NaoConformidade\SituacaoDaNaoConformidade;
use ControleConcreto\Dominio\NaoConformidade\TipoDeProvidencia;
use PDO;
use Throwable;

final class RepositorioDeNaoConformidadesEmSqlite implements RepositorioDeNaoConformidades
{
    private const FORMATO = 'Y-m-d H:i:s';

    private const COLUNAS = 'obra_codigo, lote_numero, aberta_em, fck_projeto_mpa, fck_estimado_mpa,
        situacao, desfecho, parecer, encerrada_em';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(NaoConformidade $naoConformidade): void
    {
        $this->emTransacao(function () use ($naoConformidade): void {
            $comando = $this->conexao->prepare(
                'INSERT INTO nao_conformidades (' . self::COLUNAS . ')
                 VALUES (:obra_codigo, :lote_numero, :aberta_em, :fck_projeto_mpa, :fck_estimado_mpa,
                         :situacao, :desfecho, :parecer, :encerrada_em)
                 ON CONFLICT (obra_codigo, lote_numero) DO UPDATE SET
                    situacao     = excluded.situacao,
                    desfecho     = excluded.desfecho,
                    parecer      = excluded.parecer,
                    encerrada_em = excluded.encerrada_em'
            );

            $comando->execute([
                ':obra_codigo' => $naoConformidade->obraCodigo,
                ':lote_numero' => $naoConformidade->loteNumero,
                ':aberta_em' => $naoConformidade->abertaEm->format(self::FORMATO),
                ':fck_projeto_mpa' => $naoConformidade->fckDeProjetoEmMPa,
                ':fck_estimado_mpa' => $naoConformidade->fckEstimadoEmMPa,
                ':situacao' => $naoConformidade->situacao()->value,
                ':desfecho' => $naoConformidade->desfecho()?->value,
                ':parecer' => $naoConformidade->parecer(),
                ':encerrada_em' => $naoConformidade->encerradaEm()?->format(self::FORMATO),
            ]);

            // Providência é imutável e numerada pela posição: a que já está
            // gravada não muda, e a nova entra com o próximo número.
            $inserir = $this->conexao->prepare(
                'INSERT INTO providencias (obra_codigo, lote_numero, numero, tipo, realizada_em,
                                           descricao, resultado, responsavel, fck_obtido_mpa)
                 VALUES (:obra_codigo, :lote_numero, :numero, :tipo, :realizada_em,
                         :descricao, :resultado, :responsavel, :fck_obtido_mpa)
                 ON CONFLICT (obra_codigo, lote_numero, numero) DO NOTHING'
            );

            foreach ($naoConformidade->providencias() as $indice => $providencia) {
                $inserir->execute([
                    ':obra_codigo' => $naoConformidade->obraCodigo,
                    ':lote_numero' => $naoConformidade->loteNumero,
                    ':numero' => $indice + 1,
                    ':tipo' => $providencia->tipo->value,
                    ':realizada_em' => $providencia->realizadaEm->format(self::FORMATO),
                    ':descricao' => $providencia->descricao,
                    ':resultado' => $providencia->resultado->value,
                    ':responsavel' => $providencia->responsavel,
                    ':fck_obtido_mpa' => $providencia->fckObtidoEmMPa,
                ]);
            }
        });
    }

    public function doLote(string $obraCodigo, int $loteNumero): ?NaoConformidade
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM nao_conformidades
             WHERE obra_codigo = :obra_codigo AND lote_numero = :lote_numero'
        );
        $consulta->execute([':obra_codigo' => strtoupper(trim($obraCodigo)), ':lote_numero' => $loteNumero]);

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->montar($linha);
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM nao_conformidades
             WHERE obra_codigo = :obra_codigo ORDER BY aberta_em DESC, lote_numero DESC'
        );
        $consulta->execute([':obra_codigo' => strtoupper(trim($obraCodigo))]);

        return array_map($this->montar(...), $consulta->fetchAll());
    }

    public function abertas(): array
    {
        $consulta = $this->conexao->query(
            'SELECT ' . self::COLUNAS . ' FROM nao_conformidades
             WHERE situacao = \'aberta\' ORDER BY aberta_em, obra_codigo, lote_numero'
        );

        return array_map($this->montar(...), $consulta === false ? [] : $consulta->fetchAll());
    }

    /** @param array<string, mixed> $l */
    private function montar(array $l): NaoConformidade
    {
        $consulta = $this->conexao->prepare(
            'SELECT tipo, realizada_em, descricao, resultado, responsavel, fck_obtido_mpa
             FROM providencias
             WHERE obra_codigo = :obra_codigo AND lote_numero = :lote_numero
             ORDER BY numero'
        );
        $consulta->execute([':obra_codigo' => $l['obra_codigo'], ':lote_numero' => $l['lote_numero']]);

        $providencias = array_map(
            static fn (array $p): Providencia => new Providencia(
                TipoDeProvidencia::from((string) $p['tipo']),
                new DateTimeImmutable((string) $p['realizada_em']),
                (string) $p['descricao'],
                ResultadoDaProvidencia::from((string) $p['resultado']),
                (string) $p['responsavel'],
                $p['fck_obtido_mpa'] === null ? null : (float) $p['fck_obtido_mpa'],
            ),
            $consulta->fetchAll(),
        );

        return NaoConformidade::reconstituir(
            (string) $l['obra_codigo'],
            (int) $l['lote_numero'],
            new DateTimeImmutable((string) $l['aberta_em']),
            (float) $l['fck_projeto_mpa'],
            (float) $l['fck_estimado_mpa'],
            $providencias,
            SituacaoDaNaoConformidade::from((string) $l['situacao']),
            $l['desfecho'] === null ? null : Desfecho::from((string) $l['desfecho']),
            $l['parecer'] === null ? null : (string) $l['parecer'],
            $l['encerrada_em'] === null ? null : new DateTimeImmutable((string) $l['encerrada_em']),
        );
    }

    private function emTransacao(callable $acao): void
    {
        if ($this->conexao->inTransaction()) {
            $acao();

            return;
        }

        $this->conexao->beginTransaction();

        try {
            $acao();
            $this->conexao->commit();
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }
}
