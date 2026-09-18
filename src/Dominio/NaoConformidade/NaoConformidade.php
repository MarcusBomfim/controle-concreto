<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\NaoConformidade;

use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * O que acontece quando o lote é reprovado.
 *
 * fck estimado abaixo do fck de projeto não é o fim da história: é o começo
 * de um processo com passos definidos pela norma, e cada passo precisa ficar
 * registrado — é o que o engenheiro apresenta quando a estrutura for
 * questionada. A não conformidade nasce junto com o veredito do lote e só
 * se encerra com um desfecho sustentado por uma providência favorável.
 */
final class NaoConformidade
{
    public readonly string $obraCodigo;
    public readonly int $loteNumero;
    public readonly DateTimeImmutable $abertaEm;
    public readonly float $fckDeProjetoEmMPa;
    public readonly float $fckEstimadoEmMPa;

    private SituacaoDaNaoConformidade $situacao = SituacaoDaNaoConformidade::Aberta;

    /** @var Providencia[] */
    private array $providencias = [];

    private ?Desfecho $desfecho = null;
    private ?string $parecer = null;
    private ?DateTimeImmutable $encerradaEm = null;

    private function __construct(
        string $obraCodigo,
        int $loteNumero,
        DateTimeImmutable $abertaEm,
        float $fckDeProjetoEmMPa,
        float $fckEstimadoEmMPa,
    ) {
        $this->obraCodigo = strtoupper(Regras::textoObrigatorio($obraCodigo, 'Código da obra', 20));
        $this->loteNumero = Regras::inteiroPositivo($loteNumero, 'Número do lote');
        $this->abertaEm = $abertaEm;
        $this->fckDeProjetoEmMPa = Regras::numeroPositivo($fckDeProjetoEmMPa, 'fck de projeto');
        $this->fckEstimadoEmMPa = Regras::naoNegativo($fckEstimadoEmMPa, 'fck estimado');
    }

    /** Abre a não conformidade de um lote que ficou abaixo do fck. */
    public static function abrir(
        string $obraCodigo,
        int $loteNumero,
        DateTimeImmutable $abertaEm,
        float $fckDeProjetoEmMPa,
        float $fckEstimadoEmMPa,
    ): self {
        if ($fckEstimadoEmMPa >= $fckDeProjetoEmMPa - Regras::TOLERANCIA) {
            throw new ExcecaoDeDominio(sprintf(
                'O lote atingiu %s MPa contra %s MPa de projeto: está conforme, não há o que abrir.',
                number_format($fckEstimadoEmMPa, 1, ',', '.'),
                number_format($fckDeProjetoEmMPa, 1, ',', '.'),
            ));
        }

        return new self($obraCodigo, $loteNumero, $abertaEm, $fckDeProjetoEmMPa, $fckEstimadoEmMPa);
    }

    /** @param Providencia[] $providencias */
    public static function reconstituir(
        string $obraCodigo,
        int $loteNumero,
        DateTimeImmutable $abertaEm,
        float $fckDeProjetoEmMPa,
        float $fckEstimadoEmMPa,
        array $providencias,
        SituacaoDaNaoConformidade $situacao,
        ?Desfecho $desfecho,
        ?string $parecer,
        ?DateTimeImmutable $encerradaEm,
    ): self {
        $naoConformidade = new self($obraCodigo, $loteNumero, $abertaEm, $fckDeProjetoEmMPa, $fckEstimadoEmMPa);
        $naoConformidade->providencias = array_values($providencias);
        $naoConformidade->situacao = $situacao;
        $naoConformidade->desfecho = $desfecho;
        $naoConformidade->parecer = $parecer;
        $naoConformidade->encerradaEm = $encerradaEm;

        return $naoConformidade;
    }

    public function registrarProvidencia(Providencia $providencia): void
    {
        $this->exigirAberta('registrar providência');

        // Compara dias, não instantes: a providência do mesmo dia da abertura vale.
        if ($providencia->realizadaEm->setTime(0, 0) < $this->abertaEm->setTime(0, 0)) {
            throw new ExcecaoDeDominio(sprintf(
                'A providência é de %s, antes da não conformidade ser aberta, em %s.',
                $providencia->realizadaEm->format('d/m/Y'),
                $this->abertaEm->format('d/m/Y'),
            ));
        }

        $this->providencias[] = $providencia;
    }

    /**
     * Encerra com um desfecho — e o desfecho precisa de prova. Aceitar a
     * estrutura sem revisão de projeto ou testemunho favorável seria aceitar
     * no grito, que é exatamente o que este registro existe para impedir.
     */
    public function encerrar(Desfecho $desfecho, string $parecer, DateTimeImmutable $em): void
    {
        $this->exigirAberta('encerrar');

        if ($this->providencias === []) {
            throw new ExcecaoDeDominio(
                'Não há providência registrada. Uma não conformidade não se encerra sem tratamento.'
            );
        }

        if (!$this->temProvidenciaFavoravelPara($desfecho)) {
            throw new ExcecaoDeDominio(sprintf(
                'O desfecho "%s" precisa de %s com resultado favorável, e não há.',
                $desfecho->rotulo(),
                implode(' ou ', array_map(
                    static fn (TipoDeProvidencia $tipo): string => mb_strtolower($tipo->rotulo()),
                    $desfecho->providenciasQueSustentam(),
                )),
            ));
        }

        $this->parecer = Regras::textoObrigatorio($parecer, 'Parecer de encerramento', 2000);
        $this->desfecho = $desfecho;
        $this->encerradaEm = $em;
        $this->situacao = SituacaoDaNaoConformidade::Encerrada;
    }

    /** @return Providencia[] */
    public function providencias(): array
    {
        return $this->providencias;
    }

    public function situacao(): SituacaoDaNaoConformidade
    {
        return $this->situacao;
    }

    public function estaAberta(): bool
    {
        return $this->situacao === SituacaoDaNaoConformidade::Aberta;
    }

    public function desfecho(): ?Desfecho
    {
        return $this->desfecho;
    }

    public function parecer(): ?string
    {
        return $this->parecer;
    }

    public function encerradaEm(): ?DateTimeImmutable
    {
        return $this->encerradaEm;
    }

    /** Quanto faltou, em MPa e em percentual do fck: o tamanho do problema. */
    public function deficitEmMPa(): float
    {
        return round($this->fckDeProjetoEmMPa - $this->fckEstimadoEmMPa, 1);
    }

    public function deficitPercentual(): float
    {
        return round($this->deficitEmMPa() / $this->fckDeProjetoEmMPa * 100, 1);
    }

    /** @return Desfecho[] os desfechos que as providências registradas já sustentam */
    public function desfechosPossiveis(): array
    {
        return array_values(array_filter(
            Desfecho::cases(),
            fn (Desfecho $desfecho): bool => $this->temProvidenciaFavoravelPara($desfecho),
        ));
    }

    private function temProvidenciaFavoravelPara(Desfecho $desfecho): bool
    {
        foreach ($this->providencias as $providencia) {
            if ($providencia->foiFavoravel() && in_array($providencia->tipo, $desfecho->providenciasQueSustentam(), true)) {
                return true;
            }
        }

        return false;
    }

    private function exigirAberta(string $acao): void
    {
        if ($this->situacao === SituacaoDaNaoConformidade::Aberta) {
            return;
        }

        throw new ExcecaoDeDominio(sprintf(
            'A não conformidade do lote %d já foi encerrada (%s) e não é possível %s.',
            $this->loteNumero,
            mb_strtolower($this->desfecho?->rotulo() ?? ''),
            $acao,
        ));
    }
}
