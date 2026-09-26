<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Dominio\Concretagem\Carga;
use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\MotivoDeDevolucao;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Concretagem\SituacaoDaConcretagem;
use App\Dominio\Ensaio\CorpoDeProva;
use App\Dominio\Ensaio\DiametroDoCorpoDeProva;
use App\Dominio\Ensaio\Exemplar;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\ResultadoDeEnsaio;
use App\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use App\Models\Elemento as ElementoRegistro;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * A concretagem é um agregado que atravessa quatro tabelas: concretagens,
 * cargas, exemplares e corpos_de_prova.
 *
 * Aqui o Eloquent sai e entra o Query Builder. O motivo é direto: um model
 * do Eloquent é um registro de uma tabela, e este repositório não grava
 * registros soltos — grava um agregado inteiro, numa transação, com quatro
 * upserts em lote. Usar `hasMany` obrigaria a instanciar milhares de models
 * só para descartá-los depois da tradução para o domínio.
 *
 * O Query Builder dá o mesmo SQL de antes sem string concatenada na mão, e
 * é a escolha certa quando o que se persiste não é uma linha, mas um grafo.
 */
final class RepositorioDeConcretagensEmBanco implements RepositorioDeConcretagens
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function salvar(Concretagem $concretagem): int
    {
        return DB::transaction(function () use ($concretagem): int {
            $numero = $concretagem->numero() > 0
                ? $concretagem->numero()
                : $this->proximoNumero($concretagem->obraCodigo);

            // Só a situação muda numa concretagem já gravada: o resto é
            // imutável, e reescrever abriria caminho para corromper histórico.
            DB::table('concretagens')->upsert(
                [[
                    'obra_codigo' => $concretagem->obraCodigo,
                    'numero' => $numero,
                    'elemento_codigo' => $concretagem->elemento->codigo,
                    'data' => $concretagem->data->format('Y-m-d'),
                    'fornecedor' => $concretagem->fornecedor,
                    'responsavel' => $concretagem->responsavel,
                    'situacao' => $concretagem->situacao()->value,
                ]],
                ['obra_codigo', 'numero'],
                ['situacao'],
            );

            $this->gravarCargas($concretagem, $numero);
            $this->gravarExemplares($concretagem, $numero);

            return $numero;
        });
    }

    public function porNumero(string $obraCodigo, int $numero): ?Concretagem
    {
        $linhas = $this->selecao()
            ->where('c.obra_codigo', self::normalizar($obraCodigo))
            ->where('c.numero', $numero)
            ->get();

        return $this->montarVarias($linhas->all())[0] ?? null;
    }

    public function daObra(string $obraCodigo): array
    {
        $linhas = $this->selecao()
            ->where('c.obra_codigo', self::normalizar($obraCodigo))
            ->orderByDesc('c.data')
            ->orderByDesc('c.numero')
            ->get();

        return $this->montarVarias($linhas->all());
    }

    public function doElemento(string $obraCodigo, string $elementoCodigo): array
    {
        $linhas = $this->selecao()
            ->where('c.obra_codigo', self::normalizar($obraCodigo))
            ->where('c.elemento_codigo', self::normalizar($elementoCodigo))
            ->orderBy('c.data')
            ->orderBy('c.numero')
            ->get();

        return $this->montarVarias($linhas->all());
    }

    /** A concretagem sempre vem com o elemento: é dele a especificação que ela julga. */
    private function selecao(): \Illuminate\Database\Query\Builder
    {
        return DB::table('concretagens as c')
            ->join('elementos as e', function ($juncao): void {
                $juncao->on('e.obra_codigo', '=', 'c.obra_codigo')
                    ->on('e.codigo', '=', 'c.elemento_codigo');
            })
            ->select(
                'c.obra_codigo', 'c.numero', 'c.data', 'c.fornecedor', 'c.responsavel', 'c.situacao',
                'e.codigo', 'e.tipo', 'e.descricao', 'e.pavimento', 'e.fck',
                'e.abatimento_mm', 'e.volume_previsto_m3',
            );
    }

    private function proximoNumero(string $obraCodigo): int
    {
        return ((int) DB::table('concretagens')
            ->where('obra_codigo', $obraCodigo)
            ->max('numero')) + 1;
    }

    private function gravarCargas(Concretagem $concretagem, int $numero): void
    {
        $linhas = [];

        foreach ($concretagem->cargas() as $carga) {
            $linhas[] = [
                'obra_codigo' => $concretagem->obraCodigo,
                'concretagem_numero' => $numero,
                'numero' => $carga->numero,
                'nota_fiscal' => $carga->notaFiscal,
                'placa' => $carga->placa,
                'volume_m3' => $carga->volumeEmM3,
                'saida_da_usina' => $carga->saidaDaUsina->format(self::FORMATO),
                'chegada' => $carga->chegada->format(self::FORMATO),
                'abatimento_mm' => $carga->abatimentoMedidoEmMm,
                'devolucao' => $carga->devolucao?->value,
                'observacao' => $carga->observacao,
            ];
        }

        if ($linhas === []) {
            return;
        }

        /*
         * A carga é imutável: gravada uma vez, não muda. Por isso
         * insertOrIgnore, e não upsert — o upsert do Laravel com a lista de
         * atualização vazia vira um insert comum, que estoura na segunda
         * gravação da mesma concretagem.
         */
        DB::table('cargas')->insertOrIgnore($linhas);
    }

    private function gravarExemplares(Concretagem $concretagem, int $numero): void
    {
        $exemplares = [];
        $corposDeProva = [];

        foreach ($concretagem->exemplares() as $exemplar) {
            $exemplares[] = [
                'obra_codigo' => $concretagem->obraCodigo,
                'concretagem_numero' => $numero,
                'carga_numero' => $exemplar->cargaNumero,
                'idade_dias' => $exemplar->idade->dias(),
                'moldado_em' => $exemplar->moldadoEm->format(self::FORMATO),
            ];

            foreach (['A' => $exemplar->primeiro, 'B' => $exemplar->segundo] as $letra => $corpoDeProva) {
                $corposDeProva[] = [
                    'obra_codigo' => $concretagem->obraCodigo,
                    'concretagem_numero' => $numero,
                    'carga_numero' => $exemplar->cargaNumero,
                    'idade_dias' => $exemplar->idade->dias(),
                    'letra' => $letra,
                    'identificacao' => $corpoDeProva->identificacao,
                    'moldado_em' => $corpoDeProva->moldadoEm->format(self::FORMATO),
                    // Colunas derivadas, gravadas para o índice da agenda existir.
                    'rompimento_previsto' => $corpoDeProva->rompimentoPrevisto()->format(self::FORMATO),
                    'inicio_janela' => $corpoDeProva->inicioDaJanela()->format(self::FORMATO),
                    'fim_janela' => $corpoDeProva->fimDaJanela()->format(self::FORMATO),
                    'situacao' => $corpoDeProva->situacao()->value,
                    'rompido_em' => $corpoDeProva->resultado()?->rompidoEm->format(self::FORMATO),
                    'carga_kn' => $corpoDeProva->resultado()?->cargaDeRupturaEmKN,
                    'diametro_mm' => $corpoDeProva->resultado()?->diametro->value,
                    'resistencia_mpa' => $corpoDeProva->resultado()?->resistenciaEmMPa(),
                    'motivo_descarte' => $corpoDeProva->motivoDoDescarte(),
                ];
            }
        }

        if ($exemplares === []) {
            return;
        }

        // O exemplar também é imutável: moldado uma vez, fica como está.
        DB::table('exemplares')->insertOrIgnore($exemplares);

        // O corpo de prova muda: cura, rompe ou é descartado. Só o resultado
        // e a situação são atualizados; a janela é imutável.
        DB::table('corpos_de_prova')->upsert(
            $corposDeProva,
            ['obra_codigo', 'concretagem_numero', 'carga_numero', 'idade_dias', 'letra'],
            ['rompido_em', 'carga_kn', 'diametro_mm', 'resistencia_mpa', 'motivo_descarte', 'situacao'],
        );
    }

    /**
     * Recompõe várias concretagens carregando os filhos em duas consultas,
     * e não em duas por concretagem. É a diferença entre uma listagem que
     * abre e uma que trava com cem concretagens na tela.
     *
     * @param  array<int, object> $linhas
     * @return Concretagem[]
     */
    private function montarVarias(array $linhas): array
    {
        if ($linhas === []) {
            return [];
        }

        $obraCodigo = (string) $linhas[0]->obra_codigo;
        $numeros = array_map(static fn (object $l): int => (int) $l->numero, $linhas);

        $cargas = $this->carregarCargas($obraCodigo, $numeros);
        $exemplares = $this->carregarExemplares($obraCodigo, $numeros);

        $concretagens = [];

        foreach ($linhas as $linha) {
            $numero = (int) $linha->numero;

            $concretagem = Concretagem::reconstituir(
                $obraCodigo,
                $numero,
                RepositorioDeElementosEmBanco::montar(new ElementoRegistro((array) $linha)),
                new DateTimeImmutable((string) $linha->data),
                (string) $linha->fornecedor,
                (string) $linha->responsavel,
                SituacaoDaConcretagem::from((string) $linha->situacao),
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
        $linhas = DB::table('cargas')
            ->where('obra_codigo', $obraCodigo)
            ->whereIn('concretagem_numero', $numeros)
            ->orderBy('numero')
            ->get();

        $porConcretagem = [];

        foreach ($linhas as $l) {
            $porConcretagem[(int) $l->concretagem_numero][] = new Carga(
                (int) $l->numero,
                (string) $l->nota_fiscal,
                $l->placa === null ? null : (string) $l->placa,
                (float) $l->volume_m3,
                new DateTimeImmutable((string) $l->saida_da_usina),
                new DateTimeImmutable((string) $l->chegada),
                (int) $l->abatimento_mm,
                $l->devolucao === null ? null : MotivoDeDevolucao::from((string) $l->devolucao),
                $l->observacao === null ? null : (string) $l->observacao,
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
        $linhas = DB::table('exemplares as e')
            ->join('corpos_de_prova as cp', function ($juncao): void {
                $juncao->on('cp.obra_codigo', '=', 'e.obra_codigo')
                    ->on('cp.concretagem_numero', '=', 'e.concretagem_numero')
                    ->on('cp.carga_numero', '=', 'e.carga_numero')
                    ->on('cp.idade_dias', '=', 'e.idade_dias');
            })
            ->select(
                'e.concretagem_numero', 'e.carga_numero', 'e.idade_dias', 'e.moldado_em',
                'cp.letra', 'cp.identificacao', 'cp.situacao',
                'cp.rompido_em', 'cp.carga_kn', 'cp.diametro_mm', 'cp.motivo_descarte',
            )
            ->where('e.obra_codigo', $obraCodigo)
            ->whereIn('e.concretagem_numero', $numeros)
            ->orderBy('e.carga_numero')
            ->orderBy('e.idade_dias')
            ->orderBy('cp.letra')
            ->get();

        // Duas linhas por exemplar (A e B): agrupa antes de montar.
        $agrupado = [];

        foreach ($linhas as $l) {
            $chave = sprintf('%d-%d-%d', $l->concretagem_numero, $l->carga_numero, $l->idade_dias);

            $agrupado[$chave]['meta'] = $l;
            $agrupado[$chave]['cps'][(string) $l->letra] = CorpoDeProva::reconstituir(
                (string) $l->identificacao,
                new DateTimeImmutable((string) $l->moldado_em),
                IdadeDeEnsaio::from((int) $l->idade_dias),
                SituacaoDoCorpoDeProva::from((string) $l->situacao),
                self::montarResultado($l),
                $l->motivo_descarte === null ? null : (string) $l->motivo_descarte,
            );
        }

        $porConcretagem = [];

        foreach ($agrupado as $grupo) {
            $meta = $grupo['meta'];

            $porConcretagem[(int) $meta->concretagem_numero][] = Exemplar::reconstituir(
                (int) $meta->carga_numero,
                IdadeDeEnsaio::from((int) $meta->idade_dias),
                new DateTimeImmutable((string) $meta->moldado_em),
                $grupo['cps']['A'],
                $grupo['cps']['B'],
            );
        }

        return $porConcretagem;
    }

    /**
     * O resultado só existe quando as três colunas existem — o gatilho do
     * banco garante que ou vêm todas, ou não vem nenhuma.
     */
    private static function montarResultado(object $l): ?ResultadoDeEnsaio
    {
        if ($l->rompido_em === null || $l->carga_kn === null || $l->diametro_mm === null) {
            return null;
        }

        return new ResultadoDeEnsaio(
            (float) $l->carga_kn,
            DiametroDoCorpoDeProva::from((int) $l->diametro_mm),
            new DateTimeImmutable((string) $l->rompido_em),
        );
    }

    private static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }
}
