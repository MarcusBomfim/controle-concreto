<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Ensaio\CorpoDeProva;
use App\Dominio\Ensaio\Exemplar;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use App\Dominio\ExcecaoDeDominio;
use DateInterval;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class EnsaioTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    // ---------- Idade de ensaio ----------

    #[Test]
    public function a_tolerancia_de_rompimento_cresce_com_a_idade(): void
    {
        $this->assertAproximado(0.5, IdadeDeEnsaio::UmDia->toleranciaEmHoras());
        $this->assertAproximado(6.0, IdadeDeEnsaio::SeteDias->toleranciaEmHoras());
        $this->assertAproximado(20.0, IdadeDeEnsaio::VinteEOitoDias->toleranciaEmHoras());
        $this->assertAproximado(48.0, IdadeDeEnsaio::NoventaEUmDias->toleranciaEmHoras());
    }

    #[Test]
    public function so_28_dias_e_idade_de_aceitacao(): void
    {
        $this->assertTrue(IdadeDeEnsaio::VinteEOitoDias->ehDeAceitacao(), '28 dias');
        $this->assertFalse(IdadeDeEnsaio::SeteDias->ehDeAceitacao(), '7 dias é informação');
        $this->assertFalse(IdadeDeEnsaio::SessentaETresDias->ehDeAceitacao(), '63 dias é complementar');
    }

    #[Test]
    public function recusa_idade_fora_das_previstas(): void
    {
        $this->recusa( fn () => IdadeDeEnsaio::deDias(14), 'Não há ensaio previsto');
    }

    // ---------- Corpo de prova: a janela de rompimento ----------

    #[Test]
    public function o_rompimento_previsto_e_a_moldagem_mais_a_idade(): void
    {
        $cp = new CorpoDeProva('C1-28d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

        $this->assertSame('2026-04-07 09:00', $cp->rompimentoPrevisto()->format('Y-m-d H:i'));
    }

    #[Test]
    public function a_janela_de_28_dias_e_de_20_horas_para_cada_lado(): void
    {
        $cp = new CorpoDeProva('C1-28d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

        $this->assertSame('2026-04-06 13:00', $cp->inicioDaJanela()->format('Y-m-d H:i'));
        $this->assertSame('2026-04-08 05:00', $cp->fimDaJanela()->format('Y-m-d H:i'));
    }

    #[Test]
    public function a_janela_de_24_horas_lida_com_meia_hora(): void
    {
        // DateInterval não aceita 0,5 h. Se o deslocamento estiver errado, é aqui que aparece.
        $cp = new CorpoDeProva('C1-1d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::UmDia);

        $this->assertSame('2026-03-11 08:30', $cp->inicioDaJanela()->format('Y-m-d H:i'));
        $this->assertSame('2026-03-11 09:30', $cp->fimDaJanela()->format('Y-m-d H:i'));
    }

    #[Test]
    public function reconhece_o_momento_dentro_e_fora_da_janela(): void
    {
        $cp = new CorpoDeProva('C1-28d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

        $this->assertTrue($cp->dentroDaJanela($this->momento('2026-04-07 09:00')), 'no instante exato');
        $this->assertTrue($cp->dentroDaJanela($this->momento('2026-04-06 13:00')), 'limite inferior');
        $this->assertTrue($cp->dentroDaJanela($this->momento('2026-04-08 05:00')), 'limite superior');
        $this->assertFalse($cp->dentroDaJanela($this->momento('2026-04-06 12:59')), 'um minuto antes');
        $this->assertFalse($cp->dentroDaJanela($this->momento('2026-04-08 05:01')), 'um minuto depois');
    }

    #[Test]
    public function sabe_quando_ainda_e_cedo(): void
    {
        $cp = new CorpoDeProva('C1-7d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::SeteDias);

        $this->assertTrue($cp->aindaNaoPodeRomper($this->momento('2026-03-15 09:00')), 'cinco dias');
        $this->assertFalse($cp->aindaNaoPodeRomper($this->momento('2026-03-17 04:00')), 'já dentro da janela de ±6h');
    }

    #[Test]
    public function vence_quando_a_janela_passa_sem_rompimento(): void
    {
        $cp = new CorpoDeProva('C1-7d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::SeteDias);

        $this->assertFalse($cp->estaVencido($this->momento('2026-03-17 15:00')), 'ainda na janela');
        $this->assertTrue($cp->estaVencido($this->momento('2026-03-17 15:01')), 'passou');
        $this->assertSame(SituacaoDoCorpoDeProva::Curando, $cp->situacao(), 'continua curando, mas perdeu a idade');
    }

    #[Test]
    public function conta_as_horas_ate_a_janela_abrir(): void
    {
        $cp = new CorpoDeProva('C1-28d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

        $this->assertAproximado(24.0, $cp->horasAteAJanela($this->momento('2026-04-05 13:00')));
        $this->assertAproximado(-2.0, $cp->horasAteAJanela($this->momento('2026-04-06 15:00')), 'negativo quando já abriu');
    }

    // ---------- Exemplar ----------

    #[Test]
    public function molda_dois_corpos_de_prova_com_identificacao_derivada(): void
    {
        $exemplar = Exemplar::moldar(2, IdadeDeEnsaio::VinteEOitoDias, $this->momento('2026-03-10 09:00'));

        $this->assertSame('C2-28d-A', $exemplar->primeiro->identificacao);
        $this->assertSame('C2-28d-B', $exemplar->segundo->identificacao);
        $this->assertSame('Carga 2 · 28 dias', $exemplar->identificacao());
        $this->assertSame(2, count($exemplar->corposDeProva()));
    }

    #[Test]
    public function os_dois_corpos_de_prova_compartilham_a_janela(): void
    {
        $exemplar = Exemplar::moldar(1, IdadeDeEnsaio::SeteDias, $this->momento('2026-03-10 09:00'));

        $this->assertSame(
            $exemplar->primeiro->fimDaJanela()->format('c'),
            $exemplar->segundo->fimDaJanela()->format('c'),
        );
        $this->assertSame('2026-03-17 09:00', $exemplar->rompimentoPrevisto()->format('Y-m-d H:i'));
    }

    #[Test]
    public function exemplar_de_28_dias_e_de_aceitacao(): void
    {
        $this->assertTrue(Exemplar::moldar(1, IdadeDeEnsaio::VinteEOitoDias, $this->momento('2026-03-10 09:00'))->ehDeAceitacao(), '28');
        $this->assertFalse(Exemplar::moldar(1, IdadeDeEnsaio::SeteDias, $this->momento('2026-03-10 09:00'))->ehDeAceitacao(), '7');
    }

    #[Test]
    public function acusa_corpo_de_prova_vencido(): void
    {
        $exemplar = Exemplar::moldar(1, IdadeDeEnsaio::SeteDias, $this->momento('2026-03-10 09:00'));

        $this->assertFalse($exemplar->temCorpoDeProvaVencido($this->momento('2026-03-17 12:00')), 'na janela');
        $this->assertTrue($exemplar->temCorpoDeProvaVencido($this->momento('2026-03-18 12:00')), 'um dia depois');
    }

    // ---------- Moldagem a partir da concretagem ----------

    #[Test]
    public function molda_um_exemplar_por_idade_a_partir_de_uma_carga_aceita(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);

        $exemplares = $this->moldaPadrao($concretagem);

        $this->assertSame(2, count($exemplares), 'um de 7 e um de 28');
        $this->assertSame(4, count($concretagem->corposDeProva()), 'dois corpos de prova por exemplar');
        $this->assertSame(1, count($concretagem->exemplaresDeAceitacao()), 'só o de 28 conta');
    }

    #[Test]
    public function carga_devolvida_nao_gera_corpo_de_prova(): void
    {
        // A regra que amarra a Etapa 2 a esta: o concreto devolvido não está na peça.
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 140);

        $this->recusa(
            fn () => $this->moldaPadrao($concretagem),
            'não gera corpo de prova',
        );
        $this->assertSame([], $concretagem->exemplares());
    }

    #[Test]
    public function recusa_carga_inexistente(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);

        $this->recusa( fn () => $this->moldaPadrao($concretagem, 7), 'Não existe carga');
    }

    #[Test]
    public function recusa_moldagem_antes_da_chegada_da_carga(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100, '08:00', '08:50');

        $this->recusa(
            fn () => $concretagem->moldar(1, $this->hora('08:30'), [IdadeDeEnsaio::VinteEOitoDias]),
            'anterior à chegada',
        );
    }

    #[Test]
    public function recusa_moldagem_em_outro_dia(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);

        $this->recusa(
            fn () => $concretagem->moldar(1, $this->momento('2026-03-11 09:00'), [IdadeDeEnsaio::VinteEOitoDias]),
            'no dia da concretagem',
        );
    }

    #[Test]
    public function recusa_segundo_exemplar_da_mesma_idade_para_a_mesma_carga(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);
        $this->moldaPadrao($concretagem);

        $this->recusa(
            fn () => $concretagem->moldar(1, $this->hora('09:30'), [IdadeDeEnsaio::VinteEOitoDias]),
            'já tem exemplar de 28 dias',
        );
    }

    #[Test]
    public function recusa_lista_de_idades_vazia(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);

        $this->recusa( fn () => $concretagem->moldar(1, $this->hora('09:00'), []), 'ao menos uma idade');
    }

    #[Test]
    public function encontra_o_exemplar_por_carga_e_idade(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
        $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-2');
        $this->moldaPadrao($concretagem, 1);
        $this->moldaPadrao($concretagem, 2);

        $this->assertSame('Carga 2 · 28 dias', $concretagem->exemplar(2, IdadeDeEnsaio::VinteEOitoDias)?->identificacao());
        $this->assertSame(null, $concretagem->exemplar(3, IdadeDeEnsaio::VinteEOitoDias));
        $this->assertSame(2, count($concretagem->exemplaresDaCarga(1)));
        $this->assertSame(2, count($concretagem->exemplaresDeAceitacao()));
    }

    #[Test]
    public function concretagem_concluida_nao_molda_mais(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
        $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-2');
        $this->moldaPadrao($concretagem, 1);
        $concretagem->concluir();

        $this->recusa( fn () => $this->moldaPadrao($concretagem, 2), 'não é possível moldar');
    }
}
