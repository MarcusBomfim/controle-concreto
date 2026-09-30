<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\NaoConformidade\Desfecho;
use App\Dominio\NaoConformidade\NaoConformidade;
use App\Dominio\NaoConformidade\Providencia;
use App\Dominio\NaoConformidade\ResultadoDaProvidencia;
use App\Dominio\NaoConformidade\SituacaoDaNaoConformidade;
use App\Dominio\NaoConformidade\TipoDeProvidencia;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class NaoConformidadeTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    private function naoConformidadeDeTeste(): NaoConformidade
    {
        return NaoConformidade::abrir('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'), 30.0, 26.4);
    }

    private function providencia(
        TipoDeProvidencia $tipo,
        ResultadoDaProvidencia $resultado,
        string $data = '2026-04-15',
        ?float $fck = null,
    ): Providencia {
        return new Providencia(
            $tipo,
            $this->momento($data),
            'Descrição suficiente da providência realizada na peça.',
            $resultado,
            'Marcus Bomfim',
            $fck,
        );
    }

    // ---------- Não conformidade: abertura ----------

    #[Test]
    public function nasce_aberta_com_o_tamanho_do_problema(): void
    {
        $nc = $this->naoConformidadeDeTeste();

        $this->assertSame(SituacaoDaNaoConformidade::Aberta, $nc->situacao());
        $this->assertAproximado(3.6, $nc->deficitEmMPa());
        $this->assertAproximado(12.0, $nc->deficitPercentual());
        $this->assertSame([], $nc->providencias());
        $this->assertSame([], $nc->desfechosPossiveis(), 'nada sustenta desfecho ainda');
    }

    #[Test]
    public function nao_se_abre_para_lote_que_atendeu(): void
    {
        $this->recusa(
            fn () => NaoConformidade::abrir('OBR-2026-007', 1, $this->momento('2026-04-10'), 30.0, 30.0),
            'está conforme',
        );
    }

    // ---------- Não conformidade: providências ----------

    #[Test]
    public function registra_providencia_e_ela_sustenta_o_desfecho_correspondente(): void
    {
        $nc = $this->naoConformidadeDeTeste();

        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Favoravel));

        $this->assertSame(1, count($nc->providencias()));
        $this->assertSame([Desfecho::EstruturaAceita], $nc->desfechosPossiveis());
    }

    #[Test]
    public function providencia_desfavoravel_nao_sustenta_nada(): void
    {
        $nc = $this->naoConformidadeDeTeste();

        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Desfavoravel));

        $this->assertSame([], $nc->desfechosPossiveis());
    }

    #[Test]
    public function recusa_providencia_anterior_a_abertura(): void
    {
        $nc = $this->naoConformidadeDeTeste();

        $this->recusa(
            fn () => $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Favoravel, '2026-04-01')),
            'antes da não conformidade ser aberta',
        );
    }

    #[Test]
    public function descricao_curta_nao_e_relato(): void
    {
        $this->recusa(
            fn () => new Providencia(TipoDeProvidencia::Reforco, $this->momento('2026-04-15'), 'ok', ResultadoDaProvidencia::Favoravel, 'Marcus'),
            'ao menos 20 caracteres',
        );
    }

    #[Test]
    public function testemunho_exige_o_fck_obtido_e_so_ele_pode_informa_lo(): void
    {
        $this->recusa(
            fn () => $this->providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Favoravel),
            'Informe o fck obtido',
        );

        $this->recusa(
            fn () => $this->providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Favoravel, fck: 28.0),
            'não mede resistência',
        );

        $testemunho = $this->providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Favoravel, fck: 28.3);
        $this->assertAproximado(28.3, $testemunho->fckObtidoEmMPa ?? 0.0);
    }

    #[Test]
    public function ensaio_nao_destrutivo_so_localiza_e_sempre_informativo(): void
    {
        $this->recusa(
            fn () => $this->providencia(TipoDeProvidencia::EnsaioNaoDestrutivo, ResultadoDaProvidencia::Favoravel),
            'localiza',
        );

        $esclerometria = $this->providencia(TipoDeProvidencia::EnsaioNaoDestrutivo, ResultadoDaProvidencia::Informativo);
        $this->assertFalse($esclerometria->foiFavoravel(), 'informativo não sustenta');
    }

    // ---------- Não conformidade: encerramento ----------

    #[Test]
    public function nao_encerra_sem_providencia(): void
    {
        $nc = $this->naoConformidadeDeTeste();

        $this->recusa(
            fn () => $nc->encerrar(Desfecho::EstruturaAceita, 'Parecer', $this->momento('2026-04-20')),
            'sem tratamento',
        );
    }

    #[Test]
    public function o_desfecho_precisa_da_providencia_favoravel_que_o_sustenta(): void
    {
        $nc = $this->naoConformidadeDeTeste();
        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Desfavoravel));

        $this->recusa(
            fn () => $nc->encerrar(Desfecho::EstruturaAceita, 'Aceito no grito', $this->momento('2026-04-20')),
            'precisa de',
        );

        // Reforço executado sustenta "reforçada", mas não "aceita como está".
        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::Reforco, ResultadoDaProvidencia::Favoravel));

        $this->assertSame([Desfecho::Reforcada], $nc->desfechosPossiveis());

        $this->recusa(
            fn () => $nc->encerrar(Desfecho::EstruturaAceita, 'Parecer', $this->momento('2026-04-20')),
        );
    }

    #[Test]
    public function encerra_com_testemunho_favoravel_e_vira_registro_definitivo(): void
    {
        $nc = $this->naoConformidadeDeTeste();
        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::EnsaioNaoDestrutivo, ResultadoDaProvidencia::Informativo));
        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Favoravel, '2026-04-18', 29.1));

        $nc->encerrar(Desfecho::EstruturaAceita, 'Os testemunhos atingiram 29,1 MPa; a revisão confirmou a segurança.', $this->momento('2026-04-20 15:00'));

        $this->assertSame(SituacaoDaNaoConformidade::Encerrada, $nc->situacao());
        $this->assertSame(Desfecho::EstruturaAceita, $nc->desfecho());
        $this->assertSame('2026-04-20 15:00', $nc->encerradaEm()?->format('Y-m-d H:i'));
        $this->assertFalse($nc->estaAberta(), 'encerrada');

        $this->recusa(
            fn () => $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::Reforco, ResultadoDaProvidencia::Favoravel, '2026-04-21')),
            'já foi encerrada',
        );
    }

    #[Test]
    public function demolicao_encerra_como_demolida(): void
    {
        $nc = $this->naoConformidadeDeTeste();
        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Desfavoravel, fck: 22.0));
        $nc->registrarProvidencia($this->providencia(TipoDeProvidencia::Demolicao, ResultadoDaProvidencia::Favoravel, '2026-05-02'));

        $nc->encerrar(Desfecho::Demolida, 'Peça demolida e reconcretada; o concreto novo tem lote próprio.', $this->momento('2026-05-03'));

        $this->assertSame(Desfecho::Demolida, $nc->desfecho());
    }
}
