<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\Lote;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\NaoConformidade\NaoConformidade;
use ControleConcreto\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use PDO;
use Throwable;

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
        private readonly PDO $conexao,
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

        $this->conexao->beginTransaction();

        try {
            $this->lotes->salvar($lote);

            if (!$lote->foiAceito()) {
                $this->naoConformidades->salvar(NaoConformidade::abrir(
                    $lote->obraCodigo,
                    $lote->numero(),
                    $momento,
                    $lote->classe->fck(),
                    $estimativa->fckEstimadoEmMPa,
                ));
            }

            $this->conexao->commit();
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }

        return $lote;
    }
}
