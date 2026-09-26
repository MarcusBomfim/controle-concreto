<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Aplicacao\AgendaDoLaboratorio;
use App\Aplicacao\ItemDaAgenda;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A agenda não hidrata agregado nenhum.
 *
 * Ela responde "o que rompe hoje?" com uma consulta que junta cinco tabelas
 * e devolve linhas prontas para a tela. Montar a Concretagem inteira — com
 * cargas, exemplares e corpos de prova — só para listar cilindros seria
 * carregar um grafo para ler cinco campos.
 *
 * É a distinção entre modelo de escrita e modelo de leitura: o repositório
 * de concretagens existe para o domínio agir; este existe para a tela
 * mostrar. Usam o mesmo banco e nada mais.
 */
final class AgendaDoLaboratorioEmBanco implements AgendaDoLaboratorio
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function comRompimentoEntre(DateTimeImmutable $inicio, DateTimeImmutable $fim): array
    {
        return $this->selecao()
            ->where('cp.situacao', 'curando')
            ->whereBetween('cp.rompimento_previsto', [
                $inicio->format(self::FORMATO),
                $fim->format(self::FORMATO),
            ])
            ->orderBy('cp.rompimento_previsto')
            ->orderBy('cp.identificacao')
            ->get()
            ->map(self::montar(...))
            ->all();
    }

    public function vencidos(DateTimeImmutable $agora): array
    {
        return $this->selecao()
            ->where('cp.situacao', 'curando')
            ->where('cp.fim_janela', '<', $agora->format(self::FORMATO))
            ->orderBy('cp.fim_janela')
            ->orderBy('cp.identificacao')
            ->get()
            ->map(self::montar(...))
            ->all();
    }

    public function totalEmCura(): int
    {
        return DB::table('corpos_de_prova')->where('situacao', 'curando')->count();
    }

    /** Traz junto o que a pessoa da prensa precisa para achar e anotar o cilindro. */
    private function selecao(): \Illuminate\Database\Query\Builder
    {
        return DB::table('corpos_de_prova as cp')
            ->join('concretagens as c', function ($j): void {
                $j->on('c.obra_codigo', '=', 'cp.obra_codigo')
                    ->on('c.numero', '=', 'cp.concretagem_numero');
            })
            ->join('elementos as e', function ($j): void {
                $j->on('e.obra_codigo', '=', 'c.obra_codigo')
                    ->on('e.codigo', '=', 'c.elemento_codigo');
            })
            ->join('obras as o', 'o.codigo', '=', 'cp.obra_codigo')
            ->join('cargas as ca', function ($j): void {
                $j->on('ca.obra_codigo', '=', 'cp.obra_codigo')
                    ->on('ca.concretagem_numero', '=', 'cp.concretagem_numero')
                    ->on('ca.numero', '=', 'cp.carga_numero');
            })
            ->select(
                'cp.obra_codigo',
                'o.nome as obra_nome',
                'cp.concretagem_numero',
                'e.codigo as elemento_codigo',
                'e.descricao as elemento_descricao',
                'e.pavimento as elemento_pavimento',
                'e.fck',
                'cp.carga_numero',
                'ca.nota_fiscal',
                'cp.idade_dias',
                'cp.identificacao',
                'cp.moldado_em',
                'cp.rompimento_previsto',
                'cp.inicio_janela',
                'cp.fim_janela',
                'cp.situacao',
            );
    }

    private static function montar(object $l): ItemDaAgenda
    {
        $elemento = sprintf('%s — %s', $l->elemento_codigo, $l->elemento_descricao);

        if ($l->elemento_pavimento !== null && $l->elemento_pavimento !== '') {
            $elemento .= " ({$l->elemento_pavimento})";
        }

        return new ItemDaAgenda(
            (string) $l->obra_codigo,
            (string) $l->obra_nome,
            (int) $l->concretagem_numero,
            $elemento,
            (int) $l->fck,
            (int) $l->carga_numero,
            (string) $l->nota_fiscal,
            IdadeDeEnsaio::from((int) $l->idade_dias),
            (string) $l->identificacao,
            new DateTimeImmutable((string) $l->moldado_em),
            new DateTimeImmutable((string) $l->rompimento_previsto),
            new DateTimeImmutable((string) $l->inicio_janela),
            new DateTimeImmutable((string) $l->fim_janela),
            SituacaoDoCorpoDeProva::from((string) $l->situacao),
        );
    }
}
