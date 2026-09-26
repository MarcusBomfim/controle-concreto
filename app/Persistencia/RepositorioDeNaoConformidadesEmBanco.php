<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Dominio\NaoConformidade\Desfecho;
use App\Dominio\NaoConformidade\NaoConformidade;
use App\Dominio\NaoConformidade\Providencia;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\NaoConformidade\ResultadoDaProvidencia;
use App\Dominio\NaoConformidade\SituacaoDaNaoConformidade;
use App\Dominio\NaoConformidade\TipoDeProvidencia;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

final class RepositorioDeNaoConformidadesEmBanco implements RepositorioDeNaoConformidades
{
    private const FORMATO = 'Y-m-d H:i:s';

    public function salvar(NaoConformidade $naoConformidade): void
    {
        DB::transaction(function () use ($naoConformidade): void {
            DB::table('nao_conformidades')->upsert(
                [[
                    'obra_codigo' => $naoConformidade->obraCodigo,
                    'lote_numero' => $naoConformidade->loteNumero,
                    'aberta_em' => $naoConformidade->abertaEm->format(self::FORMATO),
                    'fck_projeto_mpa' => $naoConformidade->fckDeProjetoEmMPa,
                    'fck_estimado_mpa' => $naoConformidade->fckEstimadoEmMPa,
                    'situacao' => $naoConformidade->situacao()->value,
                    'desfecho' => $naoConformidade->desfecho()?->value,
                    'parecer' => $naoConformidade->parecer(),
                    'encerrada_em' => $naoConformidade->encerradaEm()?->format(self::FORMATO),
                ]],
                ['obra_codigo', 'lote_numero'],
                ['situacao', 'desfecho', 'parecer', 'encerrada_em'],
            );

            $linhas = [];

            // Providência é imutável e numerada pela posição: a que já está
            // gravada não muda, e a nova entra com o próximo número.
            foreach ($naoConformidade->providencias() as $indice => $providencia) {
                $linhas[] = [
                    'obra_codigo' => $naoConformidade->obraCodigo,
                    'lote_numero' => $naoConformidade->loteNumero,
                    'numero' => $indice + 1,
                    'tipo' => $providencia->tipo->value,
                    'realizada_em' => $providencia->realizadaEm->format(self::FORMATO),
                    'descricao' => $providencia->descricao,
                    'resultado' => $providencia->resultado->value,
                    'responsavel' => $providencia->responsavel,
                    'fck_obtido_mpa' => $providencia->fckObtidoEmMPa,
                ];
            }

            if ($linhas !== []) {
                // Providência não se edita: a já gravada fica como está.
                DB::table('providencias')->insertOrIgnore($linhas);
            }
        });
    }

    public function doLote(string $obraCodigo, int $loteNumero): ?NaoConformidade
    {
        $linha = DB::table('nao_conformidades')
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->where('lote_numero', $loteNumero)
            ->first();

        return $linha === null ? null : $this->montar($linha);
    }

    public function daObra(string $obraCodigo): array
    {
        return DB::table('nao_conformidades')
            ->where('obra_codigo', self::normalizar($obraCodigo))
            ->orderByDesc('aberta_em')
            ->orderByDesc('lote_numero')
            ->get()
            ->map($this->montar(...))
            ->all();
    }

    public function abertas(): array
    {
        return DB::table('nao_conformidades')
            ->where('situacao', 'aberta')
            ->orderBy('aberta_em')
            ->orderBy('obra_codigo')
            ->orderBy('lote_numero')
            ->get()
            ->map($this->montar(...))
            ->all();
    }

    private function montar(object $l): NaoConformidade
    {
        $providencias = DB::table('providencias')
            ->where('obra_codigo', $l->obra_codigo)
            ->where('lote_numero', $l->lote_numero)
            ->orderBy('numero')
            ->get()
            ->map(static fn (object $p): Providencia => new Providencia(
                TipoDeProvidencia::from((string) $p->tipo),
                new DateTimeImmutable((string) $p->realizada_em),
                (string) $p->descricao,
                ResultadoDaProvidencia::from((string) $p->resultado),
                (string) $p->responsavel,
                $p->fck_obtido_mpa === null ? null : (float) $p->fck_obtido_mpa,
            ))
            ->all();

        return NaoConformidade::reconstituir(
            (string) $l->obra_codigo,
            (int) $l->lote_numero,
            new DateTimeImmutable((string) $l->aberta_em),
            (float) $l->fck_projeto_mpa,
            (float) $l->fck_estimado_mpa,
            $providencias,
            SituacaoDaNaoConformidade::from((string) $l->situacao),
            $l->desfecho === null ? null : Desfecho::from((string) $l->desfecho),
            $l->parecer === null ? null : (string) $l->parecer,
            $l->encerrada_em === null ? null : new DateTimeImmutable((string) $l->encerrada_em),
        );
    }

    private static function normalizar(string $codigo): string
    {
        return mb_strtoupper(trim($codigo));
    }
}
