<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\GrupoDeSolicitacao;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\EstimativaDeFck;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\Lote\SituacaoDoLote;
use App\Dominio\Lote\TipoDeAmostragem;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * O lote referencia concretagens inteiras — com cargas, exemplares e
 * resultados —, então recompor um lote é recompor as concretagens dele. Por
 * isso este repositório recebe o de concretagens, em vez de repetir o SQL.
 *
 * Quem injeta a dependência é o Service Container, configurado no
 * AppServiceProvider: o construtor pede a interface, e o Laravel entrega a
 * implementação registrada.
 */
final class RepositorioDeLotesEmBanco implements RepositorioDeLotes
{
    public function __construct(private readonly RepositorioDeConcretagens $concretagens)
    {
    }

    public function salvar(Lote $lote): int
    {
        return DB::transaction(function () use ($lote): int {
            $numero = $lote->numero() > 0 ? $lote->numero() : $this->proximoNumero($lote->obraCodigo);

            $estimativa = $lote->estimativa();

            DB::table('lotes')->upsert(
                [[
                    'obra_codigo' => $lote->obraCodigo,
                    'numero' => $numero,
                    'fck' => $lote->classe->value,
                    'grupo' => $lote->grupo->value,
                    'condicao' => $lote->condicao->value,
                    'amostragem' => $lote->amostragem->value,
                    'situacao' => $lote->situacao()->value,
                    'fck_estimado_mpa' => $estimativa?->fckEstimadoEmMPa,
                    'julgado_em' => $lote->julgadoEm()?->format('Y-m-d H:i:s'),
                    'memoria_de_calculo' => $estimativa === null ? null : self::serializar($estimativa),
                ]],
                ['obra_codigo', 'numero'],
                ['situacao', 'fck_estimado_mpa', 'julgado_em', 'memoria_de_calculo'],
            );

            $vinculos = [];

            foreach ($lote->concretagens() as $concretagem) {
                $vinculos[] = [
                    'obra_codigo' => $lote->obraCodigo,
                    'lote_numero' => $numero,
                    'concretagem_numero' => $concretagem->numero(),
                ];
            }

            if ($vinculos !== []) {
                // insertOrIgnore: se a concretagem já está em um lote, a
                // chave primária barra e o vínculo antigo permanece.
                DB::table('lote_concretagens')->insertOrIgnore($vinculos);
            }

            return $numero;
        });
    }

    public function porNumero(string $obraCodigo, int $numero): ?Lote
    {
        $linha = DB::table('lotes')
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->where('numero', $numero)
            ->first();

        return $linha === null ? null : $this->montar($linha);
    }

    public function daObra(string $obraCodigo): array
    {
        return DB::table('lotes')
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->orderByDesc('numero')
            ->get()
            ->map($this->montar(...))
            ->all();
    }

    public function loteDaConcretagem(string $obraCodigo, int $concretagemNumero): ?int
    {
        $valor = DB::table('lote_concretagens')
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->where('concretagem_numero', $concretagemNumero)
            ->value('lote_numero');

        return $valor === null ? null : (int) $valor;
    }

    private function proximoNumero(string $obraCodigo): int
    {
        return ((int) DB::table('lotes')->where('obra_codigo', $obraCodigo)->max('numero')) + 1;
    }

    private function montar(object $l): Lote
    {
        $obraCodigo = (string) $l->obra_codigo;
        $numero = (int) $l->numero;

        $lote = Lote::reconstituir(
            $obraCodigo,
            $numero,
            ClasseDeResistencia::from((int) $l->fck),
            GrupoDeSolicitacao::from((string) $l->grupo),
            CondicaoDePreparo::from((string) $l->condicao),
            TipoDeAmostragem::from((string) $l->amostragem),
            SituacaoDoLote::from((string) $l->situacao),
            $l->memoria_de_calculo === null ? null : self::desserializar((string) $l->memoria_de_calculo),
            $l->julgado_em === null ? null : new DateTimeImmutable((string) $l->julgado_em),
        );

        $numeros = DB::table('lote_concretagens')
            ->where('obra_codigo', $obraCodigo)
            ->where('lote_numero', $numero)
            ->orderBy('concretagem_numero')
            ->pluck('concretagem_numero');

        foreach ($numeros as $concretagemNumero) {
            $concretagem = $this->concretagens->porNumero($obraCodigo, (int) $concretagemNumero);

            if ($concretagem !== null) {
                $lote->anexarConcretagem($concretagem);
            }
        }

        return $lote;
    }

    /**
     * A memória de cálculo vira JSON: é o registro de como se chegou ao
     * número, e precisa sobreviver a qualquer mudança futura na calculadora.
     */
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

    private static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }
}
