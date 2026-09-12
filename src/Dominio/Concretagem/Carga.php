<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concretagem;

use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * Um caminhão-betoneira que chegou ao canteiro.
 *
 * A carga não decide se foi aceita: quem decide é a concretagem, que conhece
 * a especificação da peça. A carga só guarda o que foi medido e o veredito.
 * É imutável de propósito — o que chegou, chegou; corrigir é registrar outra.
 */
final class Carga
{
    public readonly int $numero;
    public readonly string $notaFiscal;
    public readonly ?string $placa;
    public readonly float $volumeEmM3;
    public readonly DateTimeImmutable $saidaDaUsina;
    public readonly DateTimeImmutable $chegada;
    public readonly int $abatimentoMedidoEmMm;
    public readonly ?MotivoDeDevolucao $devolucao;
    public readonly ?string $observacao;

    public function __construct(
        int $numero,
        string $notaFiscal,
        ?string $placa,
        float $volumeEmM3,
        DateTimeImmutable $saidaDaUsina,
        DateTimeImmutable $chegada,
        int $abatimentoMedidoEmMm,
        ?MotivoDeDevolucao $devolucao,
        ?string $observacao = null,
    ) {
        $this->numero = Regras::inteiroPositivo($numero, 'Número da carga');
        $this->notaFiscal = Regras::textoObrigatorio($notaFiscal, 'Nota fiscal', 40);

        $placaLimpa = $placa === null ? '' : strtoupper(trim($placa));
        $this->placa = $placaLimpa === '' ? null : $placaLimpa;

        $this->volumeEmM3 = Regras::numeroPositivo($volumeEmM3, 'Volume da carga');

        if ($chegada < $saidaDaUsina) {
            throw new ExcecaoDeDominio(
                'A carga não pode chegar antes de sair da usina. Confira os horários.'
            );
        }

        if ($abatimentoMedidoEmMm < 0) {
            throw new ExcecaoDeDominio('O abatimento medido não pode ser negativo.');
        }

        $this->saidaDaUsina = $saidaDaUsina;
        $this->chegada = $chegada;
        $this->abatimentoMedidoEmMm = $abatimentoMedidoEmMm;
        $this->devolucao = $devolucao;

        $observacaoLimpa = $observacao === null ? '' : trim($observacao);
        $this->observacao = $observacaoLimpa === '' ? null : $observacaoLimpa;
    }

    public function foiAceita(): bool
    {
        return $this->devolucao === null;
    }

    public function foiDevolvida(): bool
    {
        return $this->devolucao !== null;
    }

    public function tempoDeTransporteEmMinutos(): int
    {
        return (int) floor(($this->chegada->getTimestamp() - $this->saidaDaUsina->getTimestamp()) / 60);
    }

    public function resumo(): string
    {
        $veredito = $this->devolucao === null
            ? 'aceita'
            : 'devolvida: ' . mb_strtolower($this->devolucao->rotulo());

        return sprintf(
            'Carga %d - NF %s - %s m³ - abatimento %d mm - %d min de transporte - %s',
            $this->numero,
            $this->notaFiscal,
            number_format($this->volumeEmM3, 1, ',', '.'),
            $this->abatimentoMedidoEmMm,
            $this->tempoDeTransporteEmMinutos(),
            $veredito,
        );
    }
}
