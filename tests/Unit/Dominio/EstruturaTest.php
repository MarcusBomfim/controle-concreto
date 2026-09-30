<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Dominio\Obra\Obra;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class EstruturaTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    // ---------- Obra ----------

    #[Test]
    public function normaliza_codigo_e_registro_para_maiusculas(): void
    {
        $obra = new Obra('obr-2026-007', 'Edifício Vista Serra', 'Construtora Vale Verde', 'Marcus Bomfim', 'crea-sp 123456/d');

        $this->assertSame('OBR-2026-007', $obra->codigo);
        $this->assertSame('CREA-SP 123456/D', $obra->registroProfissional);
    }

    #[Test]
    public function recusa_campo_obrigatorio_em_branco(): void
    {
        $this->recusa(
            fn () => new Obra('OBR-1', '', 'Cliente', 'Responsável', 'CREA-SP 1/D'),
            'Nome da obra é obrigatório',
        );
    }

    // ---------- Tipo de elemento ----------

    #[Test]
    public function pilar_e_parede_tem_lote_menor_que_laje(): void
    {
        $this->assertSame(50, TipoDeElemento::Pilar->volumeMaximoDoLoteEmM3());
        $this->assertSame(50, TipoDeElemento::Parede->volumeMaximoDoLoteEmM3());
        $this->assertSame(100, TipoDeElemento::Laje->volumeMaximoDoLoteEmM3());
        $this->assertSame(100, TipoDeElemento::Fundacao->volumeMaximoDoLoteEmM3());
    }

    #[Test]
    public function piso_pode_ser_concreto_simples_pilar_nao(): void
    {
        $this->assertFalse(TipoDeElemento::Piso->exigeConcretoEstrutural(), 'piso');
        $this->assertTrue(TipoDeElemento::Pilar->exigeConcretoEstrutural(), 'pilar');
    }

    // ---------- Elemento estrutural ----------

    #[Test]
    public function guarda_a_especificacao_de_projeto(): void
    {
        $laje = $this->lajeDeTeste();

        $this->assertSame('L3-P4', $laje->codigo);
        $this->assertSame(ClasseDeResistencia::C30, $laje->classe);
        $this->assertAproximado(30.0, $laje->fckDeProjeto());
        $this->assertSame(100, $laje->abatimento->especificadoEmMm);
    }

    #[Test]
    public function monta_a_identificacao_com_o_pavimento(): void
    {
        $this->assertSame('L3-P4 — Laje L3 (4º pavimento)', $this->lajeDeTeste()->identificacao());
    }

    #[Test]
    public function pavimento_em_branco_vira_nulo(): void
    {
        $sapata = new ElementoEstrutural(
            'SAP-01',
            TipoDeElemento::Fundacao,
            'Sapata S1',
            '   ',
            ClasseDeResistencia::C25,
            new Abatimento(80),
            3.5,
        );

        $this->assertSame(null, $sapata->pavimento);
        $this->assertSame('SAP-01 — Sapata S1', $sapata->identificacao());
    }

    #[Test]
    public function elemento_estrutural_recusa_c15(): void
    {
        $this->recusa(
            fn () => new ElementoEstrutural(
                'P1',
                TipoDeElemento::Pilar,
                'Pilar P1',
                'Térreo',
                ClasseDeResistencia::C15,
                new Abatimento(100),
                1.2,
            ),
            'exige concreto estrutural',
        );
    }

    #[Test]
    public function piso_aceita_c15(): void
    {
        $piso = new ElementoEstrutural(
            'PISO-01',
            TipoDeElemento::Piso,
            'Piso do estacionamento',
            'Subsolo',
            ClasseDeResistencia::C15,
            new Abatimento(80),
            60.0,
        );

        $this->assertSame(ClasseDeResistencia::C15, $piso->classe);
    }

    #[Test]
    public function estima_os_lotes_pelo_volume_e_pelo_tipo(): void
    {
        $this->assertSame(1, $this->lajeDeTeste(42.0)->lotesPrevistos(), '42 m³ de laje cabe em um lote de 100');
        $this->assertSame(2, $this->lajeDeTeste(142.0)->lotesPrevistos(), '142 m³ de laje precisa de dois');

        $pilares = new ElementoEstrutural(
            'P-T',
            TipoDeElemento::Pilar,
            'Pilares do térreo',
            'Térreo',
            ClasseDeResistencia::C35,
            new Abatimento(120),
            120.0,
        );

        $this->assertSame(3, $pilares->lotesPrevistos(), '120 m³ de pilar em lotes de 50');
    }

    #[Test]
    public function recusa_volume_zerado(): void
    {
        $this->recusa(fn () => $this->lajeDeTeste(0.0), 'Volume previsto');
    }
}
