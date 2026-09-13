<?php

declare(strict_types=1);

namespace ControleConcreto\Infraestrutura\Repositorio;

use DateTimeImmutable;
use ControleConcreto\Aplicacao\AgendaDoLaboratorio;
use ControleConcreto\Aplicacao\ItemDaAgenda;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use PDO;

/**
 * Consulta direto a tabela corpos_de_prova, usando as colunas derivadas e os
 * índices que existem para isto. Não hidrata agregado nenhum.
 */
final class AgendaDoLaboratorioEmSqlite implements AgendaDoLaboratorio
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function comRompimentoEntre(DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        $consulta = $this->conexao->prepare(
            self::selecao() . "
             WHERE cp.situacao = 'curando'
               AND cp.rompimento_previsto BETWEEN :inicio AND :fim
             ORDER BY cp.rompimento_previsto, cp.identificacao"
        );
        $consulta->execute([
            ':inicio' => $inicio->format(self::FORMATO),
            ':fim' => $fim->format(self::FORMATO),
        ]);

        return array_map(self::montar(...), $consulta->fetchAll());
    }

    public function vencidos(DateTimeImmutable $agora): array
    {
        $consulta = $this->conexao->prepare(
            self::selecao() . "
             WHERE cp.situacao = 'curando' AND cp.fim_janela < :agora
             ORDER BY cp.fim_janela, cp.identificacao"
        );
        $consulta->execute([':agora' => $agora->format(self::FORMATO)]);

        return array_map(self::montar(...), $consulta->fetchAll());
    }

    public function totalEmCura(): int
    {
        $consulta = $this->conexao->query(
            "SELECT COUNT(*) FROM corpos_de_prova WHERE situacao = 'curando'"
        );

        return $consulta === false ? 0 : (int) $consulta->fetchColumn();
    }

    /** Traz junto o que a pessoa da prensa precisa para achar e anotar o cilindro. */
    private static function selecao(): string
    {
        return 'SELECT cp.obra_codigo, o.nome AS obra_nome, cp.concretagem_numero,
                       e.codigo AS elemento_codigo, e.descricao AS elemento_descricao,
                       e.pavimento AS elemento_pavimento, e.fck,
                       cp.carga_numero, ca.nota_fiscal, cp.idade_dias, cp.identificacao,
                       cp.moldado_em, cp.rompimento_previsto, cp.inicio_janela, cp.fim_janela,
                       cp.situacao
                FROM corpos_de_prova cp
                JOIN concretagens c ON c.obra_codigo = cp.obra_codigo AND c.numero = cp.concretagem_numero
                JOIN elementos e ON e.obra_codigo = c.obra_codigo AND e.codigo = c.elemento_codigo
                JOIN obras o ON o.codigo = cp.obra_codigo
                JOIN cargas ca ON ca.obra_codigo = cp.obra_codigo
                             AND ca.concretagem_numero = cp.concretagem_numero
                             AND ca.numero = cp.carga_numero';
    }

    /** @param array<string, mixed> $l */
    private static function montar(array $l): ItemDaAgenda
    {
        $pavimento = $l['elemento_pavimento'];

        $elemento = sprintf('%s — %s', $l['elemento_codigo'], $l['elemento_descricao']);

        if ($pavimento !== null && $pavimento !== '') {
            $elemento .= " ({$pavimento})";
        }

        return new ItemDaAgenda(
            (string) $l['obra_codigo'],
            (string) $l['obra_nome'],
            (int) $l['concretagem_numero'],
            $elemento,
            (int) $l['fck'],
            (int) $l['carga_numero'],
            (string) $l['nota_fiscal'],
            IdadeDeEnsaio::from((int) $l['idade_dias']),
            (string) $l['identificacao'],
            new DateTimeImmutable((string) $l['moldado_em']),
            new DateTimeImmutable((string) $l['rompimento_previsto']),
            new DateTimeImmutable((string) $l['inicio_janela']),
            new DateTimeImmutable((string) $l['fim_janela']),
            SituacaoDoCorpoDeProva::from((string) $l['situacao']),
        );
    }
}
