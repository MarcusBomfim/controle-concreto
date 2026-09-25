<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Ensaio\CorpoDeProva;
use App\Dominio\Ensaio\DiametroDoCorpoDeProva;
use App\Dominio\Ensaio\Exemplar;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\ResultadoDeEnsaio;
use App\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use App\Dominio\ExcecaoDeDominio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class ResultadoTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    private function cpDe28(): CorpoDeProva
    {
        return new CorpoDeProva('C1-28d-A', $this->momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);
    }

    private function exemplarRompido(?float $primeiraCargaKN, ?float $segundaCargaKN): Exemplar
    {
        $exemplar = Exemplar::moldar(1, IdadeDeEnsaio::VinteEOitoDias, $this->momento('2026-03-10 09:00'));
        $agora = $this->momento('2026-04-07 12:00');

        if ($primeiraCargaKN !== null) {
            $exemplar->primeiro->romper(
                new ResultadoDeEnsaio($primeiraCargaKN, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:00')),
                $agora,
            );
        }

        if ($segundaCargaKN !== null) {
            $exemplar->segundo->romper(
                new ResultadoDeEnsaio($segundaCargaKN, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:05')),
                $agora,
            );
        }

        return $exemplar;
    }

    // ---------- Diâmetro do corpo de prova ----------

    #[Test]
    public function calcula_a_area_da_secao(): void
    {
        $this->assertAproximado(7853.98, round(DiametroDoCorpoDeProva::DezCentimetros->areaEmMm2(), 2));
        $this->assertAproximado(17671.46, round(DiametroDoCorpoDeProva::QuinzeCentimetros->areaEmMm2(), 2));
    }

    #[Test]
    public function recusa_diametro_fora_da_norma(): void
    {
        $this->recusa( fn () => DiametroDoCorpoDeProva::deMm(120), 'prevê 100 ou 150');
    }

    // ---------- Resultado de ensaio: a conta da prensa ----------

    #[Test]
    public function converte_forca_em_resistencia_kn_mm_mpa(): void
    {
        // 250 kN num cilindro de 10 cm: 250 000 N / 7 853,98 mm² = 31,8 MPa.
        $resultado = new ResultadoDeEnsaio(250.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 09:00'));

        $this->assertAproximado(31.8, $resultado->resistenciaEmMPa());
    }

    #[Test]
    public function o_cilindro_de_15_cm_precisa_de_mais_forca_para_a_mesma_resistencia(): void
    {
        // Área 2,25× maior: 562,5 kN dão os mesmos 31,8 MPa.
        $resultado = new ResultadoDeEnsaio(562.5, DiametroDoCorpoDeProva::QuinzeCentimetros, $this->momento('2026-04-07 09:00'));

        $this->assertAproximado(31.8, $resultado->resistenciaEmMPa());
    }

    #[Test]
    public function arredonda_a_uma_casa_decimal(): void
    {
        $resultado = new ResultadoDeEnsaio(237.3, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 09:00'));

        $this->assertAproximado(30.2, $resultado->resistenciaEmMPa());
    }

    #[Test]
    public function recusa_carga_zerada_ou_implausivel(): void
    {
        $this->recusa(
            fn () => new ResultadoDeEnsaio(0.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 09:00')),
            'maior que zero',
        );
        // 250 000 é 250 kN digitados em N: erro de unidade clássico.
        $this->recusa(
            fn () => new ResultadoDeEnsaio(250000.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 09:00')),
            'Confira a unidade',
        );
    }

    // ---------- Corpo de prova: romper ----------

    #[Test]
    public function rompe_dentro_da_janela_e_guarda_o_resultado(): void
    {
        $cp = $this->cpDe28();

        $cp->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:30')),
            $this->momento('2026-04-07 11:00'),
        );

        $this->assertSame(SituacaoDoCorpoDeProva::Rompido, $cp->situacao());
        $this->assertTrue($cp->foiRompido(), 'rompido');
        $this->assertAproximado(33.1, $cp->resistenciaEmMPa() ?? 0.0);
    }

    #[Test]
    public function aceita_rompimento_nos_limites_da_janela(): void
    {
        $cedo = $this->cpDe28();
        $cedo->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-06 13:00')),
            $this->momento('2026-04-09 00:00'),
        );
        $this->assertTrue($cedo->foiRompido(), 'limite inferior');

        $tarde = $this->cpDe28();
        $tarde->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-08 05:00')),
            $this->momento('2026-04-09 00:00'),
        );
        $this->assertTrue($tarde->foiRompido(), 'limite superior');
    }

    #[Test]
    public function recusa_rompimento_antes_da_janela(): void
    {
        $cp = $this->cpDe28();

        $this->recusa(
            fn () => $cp->romper(
                new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-05 09:00')),
                $this->momento('2026-04-09 00:00'),
            ),
            'fora da janela',
        );
        $this->assertSame(SituacaoDoCorpoDeProva::Curando, $cp->situacao(), 'continua curando');
        $this->assertSame(null, $cp->resistenciaEmMPa(), 'sem resultado');
    }

    #[Test]
    public function recusa_rompimento_depois_da_janela(): void
    {
        // É a regra central: o número existe, mas não representa 28 dias.
        $cp = $this->cpDe28();

        $this->recusa(
            fn () => $cp->romper(
                new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-10 09:00')),
                $this->momento('2026-04-11 00:00'),
            ),
            'descarte-o e registre o motivo',
        );
    }

    #[Test]
    public function recusa_rompimento_com_data_no_futuro(): void
    {
        $cp = $this->cpDe28();

        $this->recusa(
            fn () => $cp->romper(
                new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:00')),
                $this->momento('2026-04-07 09:00'),
            ),
            'ainda não chegou',
        );
    }

    #[Test]
    public function nao_rompe_duas_vezes(): void
    {
        $cp = $this->cpDe28();
        $cp->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:00')),
            $this->momento('2026-04-07 11:00'),
        );

        $this->recusa(
            fn () => $cp->romper(
                new ResultadoDeEnsaio(280.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:30')),
                $this->momento('2026-04-07 11:00'),
            ),
            'já está rompido',
        );
    }

    // ---------- Corpo de prova: descartar ----------

    #[Test]
    public function descarta_com_motivo_e_fica_sem_resultado(): void
    {
        $cp = $this->cpDe28();

        $cp->descartar('Quebrou na desforma');

        $this->assertSame(SituacaoDoCorpoDeProva::Descartado, $cp->situacao());
        $this->assertSame('Quebrou na desforma', $cp->motivoDoDescarte());
        $this->assertSame(null, $cp->resistenciaEmMPa());
    }

    #[Test]
    public function exige_motivo(): void
    {
        $cp = $this->cpDe28();

        $this->recusa( fn () => $cp->descartar('   '), 'Motivo do descarte é obrigatório');
    }

    #[Test]
    public function nao_descarta_o_que_ja_foi_rompido(): void
    {
        $cp = $this->cpDe28();
        $cp->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, $this->momento('2026-04-07 10:00')),
            $this->momento('2026-04-07 11:00'),
        );

        $this->recusa( fn () => $cp->descartar('tarde demais'), 'não pode ser descartado');
    }

    // ---------- Exemplar: a resistência é a maior ----------

    #[Test]
    public function vale_o_maior_dos_dois_corpos_de_prova(): void
    {
        // 260 kN → 33,1 MPa; 240 kN → 30,6 MPa. O menor é ruído.
        $exemplar = $this->exemplarRompido(260.0, 240.0);

        $this->assertAproximado(33.1, $exemplar->resistenciaEmMPa() ?? 0.0);
        $this->assertTrue($exemplar->estaCompleto(), 'os dois rompidos');
        $this->assertFalse($exemplar->estaIncompleto(), 'não está incompleto');
    }

    #[Test]
    public function sem_nenhum_rompido_nao_tem_resistencia(): void
    {
        $exemplar = $this->exemplarRompido(null, null);

        $this->assertSame(null, $exemplar->resistenciaEmMPa());
        $this->assertFalse($exemplar->temResultado(), 'sem resultado');
        $this->assertTrue($exemplar->aguardaRompimento(), 'aguardando');
    }

    #[Test]
    public function com_um_rompido_e_o_outro_descartado_vale_o_que_rompeu_e_fica_marcado(): void
    {
        $exemplar = $this->exemplarRompido(260.0, null);
        $exemplar->segundo->descartar('Perdido na câmara de cura');

        $this->assertAproximado(33.1, $exemplar->resistenciaEmMPa() ?? 0.0);
        $this->assertTrue($exemplar->temResultado(), 'tem resultado');
        $this->assertFalse($exemplar->estaCompleto(), 'não está completo');
        $this->assertTrue($exemplar->estaIncompleto(), 'incompleto: metade da redundância');
    }

    #[Test]
    public function com_um_rompido_e_o_outro_ainda_curando_tem_resultado_parcial(): void
    {
        $exemplar = $this->exemplarRompido(260.0, null);

        $this->assertAproximado(33.1, $exemplar->resistenciaEmMPa() ?? 0.0);
        $this->assertTrue($exemplar->aguardaRompimento(), 'o segundo ainda espera');
        $this->assertFalse($exemplar->estaIncompleto(), 'não é incompleto: ainda pode completar');
    }
}
