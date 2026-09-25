<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Ensaio\DiametroDoCorpoDeProva;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\ResultadoDeEnsaio;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\GrupoDeSolicitacao;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\SituacaoDoLote;
use App\Dominio\Lote\TipoDeAmostragem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class LoteTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    // ---------- Lote: composição ----------

    #[Test]
    public function aceita_concretagem_concluida_do_mesmo_fck_e_grupo(): void
    {
        $lote = $this->loteC30();

        $lote->adicionarConcretagem($this->concretagemComResultados(1, [32.0, 31.0]));

        $this->assertSame(1, count($lote->concretagens()));
        $this->assertAproximado(16.0, $lote->volumeEmM3());
        $this->assertSame(2, count($lote->exemplaresDeAceitacao()));
    }

    #[Test]
    public function recusa_concretagem_em_andamento(): void
    {
        $lote = $this->loteC30();
        $emAndamento = $this->concretagemDeTeste();
        $emAndamento->definirNumero(9);
        $this->chegaCarga($emAndamento, 100);

        $this->recusa( fn () => $lote->adicionarConcretagem($emAndamento), 'Só concretagem concluída');
    }

    #[Test]
    public function recusa_fck_diferente(): void
    {
        $lote = $this->loteC30();
        $pilaresC35 = $this->concretagemComResultados(2, [40.0], $this->pilaresDeTeste());

        $this->recusa( fn () => $lote->adicionarConcretagem($pilaresC35), 'Um lote tem um fck só');
    }

    #[Test]
    public function recusa_grupo_diferente(): void
    {
        // Pilares C30 são verticais; o lote é horizontal.
        $pilarC30 = new ElementoEstrutural('P-9', TipoDeElemento::Pilar, 'Pilar', null, ClasseDeResistencia::C30, new Abatimento(100), 5.0);
        $lote = $this->loteC30();

        $this->recusa(
            fn () => $lote->adicionarConcretagem($this->concretagemComResultados(3, [35.0], $pilarC30)),
            'Os grupos não se misturam',
        );
    }

    #[Test]
    public function recusa_a_mesma_concretagem_duas_vezes(): void
    {
        $lote = $this->loteC30();
        $c = $this->concretagemComResultados(1, [32.0]);
        $lote->adicionarConcretagem($c);

        $this->recusa( fn () => $lote->adicionarConcretagem($c), 'já está neste lote');
    }

    #[Test]
    public function recusa_passar_do_volume_maximo_do_grupo(): void
    {
        // Horizontal: 100 m³. Duas concretagens de 6 cargas × 8 m³ = 48 cada; a terceira estoura.
        $lote = $this->loteC30();
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [30.0, 30.0, 30.0, 30.0, 30.0, 30.0]));
        $lote->adicionarConcretagem($this->concretagemComResultados(2, [30.0, 30.0, 30.0, 30.0, 30.0, 30.0]));

        $this->assertAproximado(96.0, $lote->volumeEmM3());

        $this->recusa(
            fn () => $lote->adicionarConcretagem($this->concretagemComResultados(3, [30.0])),
            'acima do limite de 100 m³',
        );
    }

    #[Test]
    public function recusa_o_quarto_dia_de_concretagem(): void
    {
        $lote = $this->loteC30();
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [30.0], data: '2026-03-10'));
        $lote->adicionarConcretagem($this->concretagemComResultados(2, [30.0], data: '2026-03-11'));
        $lote->adicionarConcretagem($this->concretagemComResultados(3, [30.0], data: '2026-03-12'));

        $this->recusa(
            fn () => $lote->adicionarConcretagem($this->concretagemComResultados(4, [30.0], data: '2026-03-13')),
            'máximo da norma',
        );
    }

    #[Test]
    public function duas_concretagens_no_mesmo_dia_contam_um_dia_so(): void
    {
        $lote = $this->loteC30();
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [30.0], data: '2026-03-10'));
        $lote->adicionarConcretagem($this->concretagemComResultados(2, [30.0], data: '2026-03-10'));
        $lote->adicionarConcretagem($this->concretagemComResultados(3, [30.0], data: '2026-03-11'));
        $lote->adicionarConcretagem($this->concretagemComResultados(4, [30.0], data: '2026-03-12'));

        $this->assertSame(4, count($lote->concretagens()));
    }

    // ---------- Lote: julgamento ----------

    #[Test]
    public function aceita_quando_o_fck_estimado_atende_o_projeto(): void
    {
        // Seis exemplares: 32,4 29,8 31,1 33,6 30,5 35,0 → fck,est = 29,2. Lote é C25 aqui.
        $lote = new Lote('OBR-2026-007', ClasseDeResistencia::C25, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial);
        $sapata = new ElementoEstrutural('SAP', TipoDeElemento::Fundacao, 'Sapata', null, ClasseDeResistencia::C25, new Abatimento(80), 60.0);

        $lote->adicionarConcretagem($this->concretagemComResultados(1, [32.4, 29.8, 31.1], $sapata));
        $lote->adicionarConcretagem($this->concretagemComResultados(2, [33.6, 30.5, 35.0], $sapata, '2026-03-11'));

        $this->assertTrue($lote->podeSerJulgado(), 'pode julgar');

        $estimativa = $lote->julgar($this->momento('2026-04-10 10:00'));

        $this->assertAproximado(29.2, $estimativa->fckEstimadoEmMPa);
        $this->assertSame(SituacaoDoLote::Aceito, $lote->situacao());
        $this->assertTrue($lote->foiAceito(), 'aceito');
    }

    #[Test]
    public function reprova_quando_nao_atende(): void
    {
        // Os mesmos 29,2 num lote C30: não conforme.
        $lote = $this->loteC30();
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [32.4, 29.8, 31.1]));
        $lote->adicionarConcretagem($this->concretagemComResultados(2, [33.6, 30.5, 35.0], data: '2026-03-11'));

        $lote->julgar($this->momento('2026-04-10 10:00'));

        $this->assertSame(SituacaoDoLote::NaoConforme, $lote->situacao());
        $this->assertFalse($lote->foiAceito(), 'não conforme');
    }

    #[Test]
    public function nao_julga_com_exemplar_pendente(): void
    {
        $lote = $this->loteC30();
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [32.0, 31.0, null, 33.0, 30.0, 34.0]));

        $this->assertFalse($lote->podeSerJulgado(), 'tem pendente');
        $this->assertSame(1, count($lote->exemplaresPendentes()));

        $this->recusa( fn () => $lote->julgar($this->momento('2026-04-10 10:00')), 'aguardando rompimento');
    }

    #[Test]
    public function exemplar_descartado_nos_dois_corpos_de_prova_conta_como_perdido_nao_como_pendente(): void
    {
        $lote = $this->loteC30();
        $concretagem = $this->concretagemComResultados(1, [32.0, 31.0, null, 33.0, 30.0, 34.0, 32.5]);

        $exemplar = $concretagem->exemplar(3, IdadeDeEnsaio::VinteEOitoDias);
        $exemplar?->primeiro->descartar('Quebrou');
        $exemplar?->segundo->descartar('Quebrou também');

        $lote->adicionarConcretagem($concretagem);

        $this->assertSame(0, count($lote->exemplaresPendentes()));
        $this->assertSame(1, count($lote->exemplaresPerdidos()));
        $this->assertSame(6, count($lote->exemplaresComResultado()), 'seis para a conta');
        $this->assertTrue($lote->podeSerJulgado(), 'pode julgar com os seis');
    }

    #[Test]
    public function lote_vazio_nao_julga(): void
    {
        $this->recusa( fn () => $this->loteC30()->julgar($this->momento('2026-04-10 10:00')), 'está vazio');
    }

    #[Test]
    public function lote_julgado_nao_muda_mais(): void
    {
        $lote = $this->loteC30();
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [32.4, 29.8, 31.1, 33.6, 30.5, 35.0]));
        $lote->julgar($this->momento('2026-04-10 10:00'));

        $this->recusa( fn () => $lote->julgar($this->momento('2026-04-11 10:00')), 'já foi julgado');
        $this->recusa( fn () => $lote->adicionarConcretagem($this->concretagemComResultados(2, [30.0])), 'já foi julgado');
    }

    #[Test]
    public function amostragem_total_com_poucos_exemplares_vale_o_menor(): void
    {
        $lote = $this->loteC30(TipoDeAmostragem::Total);
        $lote->adicionarConcretagem($this->concretagemComResultados(1, [33.0, 31.5, 34.0]));

        $estimativa = $lote->julgar($this->momento('2026-04-10 10:00'));

        $this->assertAproximado(31.5, $estimativa->fckEstimadoEmMPa);
        $this->assertTrue($lote->foiAceito(), '31,5 ≥ 30');
    }
}
