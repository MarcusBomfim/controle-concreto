<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use DateTimeImmutable;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;

/**
 * Uma linha da agenda do laboratório: um corpo de prova e tudo que quem vai
 * romper precisa saber para achá-lo na câmara de cura e anotar o laudo.
 *
 * É modelo de leitura, não entidade. Vem de uma consulta com JOIN, pronto
 * para a tela, sem hidratar a concretagem inteira só para listar cilindros.
 */
final class ItemDaAgenda
{
    public function __construct(
        public readonly string $obraCodigo,
        public readonly string $obraNome,
        public readonly int $concretagemNumero,
        public readonly string $elementoIdentificacao,
        public readonly int $fckDeProjeto,
        public readonly int $cargaNumero,
        public readonly string $notaFiscal,
        public readonly IdadeDeEnsaio $idade,
        public readonly string $identificacao,
        public readonly DateTimeImmutable $moldadoEm,
        public readonly DateTimeImmutable $rompimentoPrevisto,
        public readonly DateTimeImmutable $inicioDaJanela,
        public readonly DateTimeImmutable $fimDaJanela,
        public readonly SituacaoDoCorpoDeProva $situacao,
    ) {
    }

    public function dentroDaJanela(DateTimeImmutable $agora): bool
    {
        return $agora >= $this->inicioDaJanela && $agora <= $this->fimDaJanela;
    }

    public function estaVencido(DateTimeImmutable $agora): bool
    {
        return $this->situacao->aguardaRompimento() && $agora > $this->fimDaJanela;
    }

    public function aindaNaoAbriu(DateTimeImmutable $agora): bool
    {
        return $agora < $this->inicioDaJanela;
    }

    /** Rótulo curto de estado para a tela: "na janela", "vencido", "em 3 dias". */
    public function estado(DateTimeImmutable $agora): string
    {
        if (!$this->situacao->aguardaRompimento()) {
            return mb_strtolower($this->situacao->rotulo());
        }

        if ($this->estaVencido($agora)) {
            return 'vencido';
        }

        if ($this->dentroDaJanela($agora)) {
            return 'na janela';
        }

        $dias = (int) ceil(($this->inicioDaJanela->getTimestamp() - $agora->getTimestamp()) / 86400);

        return $dias <= 1 ? 'amanhã' : "em {$dias} dias";
    }
}
