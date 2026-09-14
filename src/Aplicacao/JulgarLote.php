<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\Lote;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;

/**
 * Faz a conta da norma para um lote e grava o veredito.
 *
 * O caso de uso não conhece a fórmula: carrega o lote — que carrega as
 * concretagens, que carregam os exemplares com resultado — e pede para ele
 * se julgar. A memória de cálculo vai junto para o banco.
 */
final class JulgarLote
{
    public function __construct(private readonly RepositorioDeLotes $lotes)
    {
    }

    public function executar(string $obraCodigo, int $numero, ?DateTimeImmutable $agora = null): Lote
    {
        $lote = $this->lotes->porNumero($obraCodigo, $numero);

        if ($lote === null) {
            throw new ExcecaoDeDominio("Não existe lote de número {$numero} na obra {$obraCodigo}.");
        }

        $lote->julgar($agora ?? new DateTimeImmutable('now'));

        $this->lotes->salvar($lote);

        return $lote;
    }
}
