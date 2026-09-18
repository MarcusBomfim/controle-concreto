<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\NaoConformidade;

use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * Um passo do tratamento: o que foi feito, quando, por quem e o que deu.
 *
 * Imutável, como a carga devolvida: providência registrada não se edita.
 * Errou, registra outra e explica.
 */
final class Providencia
{
    /** Menos que isto não é relato, é anotação: "ok" não sustenta laudo nenhum. */
    private const DETALHE_MINIMO = 20;

    public readonly TipoDeProvidencia $tipo;
    public readonly DateTimeImmutable $realizadaEm;
    public readonly string $descricao;
    public readonly ResultadoDaProvidencia $resultado;
    public readonly string $responsavel;
    public readonly ?float $fckObtidoEmMPa;

    public function __construct(
        TipoDeProvidencia $tipo,
        DateTimeImmutable $realizadaEm,
        string $descricao,
        ResultadoDaProvidencia $resultado,
        string $responsavel,
        ?float $fckObtidoEmMPa = null,
    ) {
        $this->tipo = $tipo;
        $this->realizadaEm = $realizadaEm;
        $this->descricao = Regras::textoObrigatorio($descricao, 'Descrição da providência', 2000);
        $this->responsavel = Regras::textoObrigatorio($responsavel, 'Responsável pela providência', 160);

        if (mb_strlen($this->descricao) < self::DETALHE_MINIMO) {
            throw new ExcecaoDeDominio(sprintf(
                'Descreva a providência com ao menos %d caracteres: o que foi feito, onde e o que se concluiu.',
                self::DETALHE_MINIMO,
            ));
        }

        if ($fckObtidoEmMPa !== null) {
            if (!$tipo->informaResistencia()) {
                throw new ExcecaoDeDominio(sprintf(
                    '%s não mede resistência. O fck obtido só se informa na extração de testemunhos.',
                    $tipo->rotulo(),
                ));
            }

            Regras::numeroPositivo($fckObtidoEmMPa, 'fck obtido nos testemunhos');
        }

        if ($tipo->informaResistencia() && $fckObtidoEmMPa === null) {
            throw new ExcecaoDeDominio(
                'Informe o fck obtido nos testemunhos: é o número que sustenta a decisão.'
            );
        }

        // Ensaio que só localiza não decide nada; o resultado dele é informativo.
        if ($tipo === TipoDeProvidencia::EnsaioNaoDestrutivo && $resultado !== ResultadoDaProvidencia::Informativo) {
            throw new ExcecaoDeDominio(
                'Ensaio não destrutivo não aprova nem reprova a peça: ele localiza. Registre-o como informativo.'
            );
        }

        $this->resultado = $resultado;
        $this->fckObtidoEmMPa = $fckObtidoEmMPa;
    }

    public function foiFavoravel(): bool
    {
        return $this->resultado === ResultadoDaProvidencia::Favoravel;
    }
}
