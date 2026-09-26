<?php

declare(strict_types=1);

namespace App\Aplicacao;

use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\NaoConformidade\NaoConformidade;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Faz a conta da norma para um lote e grava o veredito.
 *
 * O caso de uso não conhece a fórmula: carrega o lote — que carrega as
 * concretagens, que carregam os exemplares com resultado — e pede para ele
 * se julgar. A memória de cálculo vai junto para o banco.
 *
 * Se o veredito for "não conforme", a não conformidade nasce aqui, na mesma
 * transação: não existe lote reprovado sem tratamento aberto. É a regra que
 * impede o resultado ruim de ser esquecido numa tabela.
 */
final class JulgarLote
{
    public function __construct(
        private readonly RepositorioDeLotes $lotes,
        private readonly RepositorioDeNaoConformidades $naoConformidades,
    ) {
    }

    public function executar(string $obraCodigo, int $numero, ?DateTimeImmutable $agora = null): Lote
    {
        $lote = $this->lotes->porNumero($obraCodigo, $numero);

        if ($lote === null) {
            throw new ExcecaoDeDominio("Não existe lote de número {$numero} na obra {$obraCodigo}.");
        }

        $momento = $agora ?? new DateTimeImmutable('now');
        $estimativa = $lote->julgar($momento);

        DB::transaction(function () use ($lote, $estimativa, $momento): void {
            $this->lotes->salvar($lote);

            if ($lote->foiAceito()) {
                return;
            }

            $this->naoConformidades->salvar(NaoConformidade::abrir(
                $lote->obraCodigo,
                $lote->numero(),
                $momento,
                $lote->classe->fck(),
                $estimativa->fckEstimadoEmMPa,
            ));
        });

        return $lote;
    }
}
