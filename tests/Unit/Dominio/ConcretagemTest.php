<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Concretagem\Carga;
use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\MotivoDeDevolucao;
use App\Dominio\Concretagem\SituacaoDaConcretagem;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class ConcretagemTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    // ---------- Concretagem: abertura ----------

    #[Test]
    public function nasce_em_andamento_e_sem_cargas(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->assertSame(SituacaoDaConcretagem::EmAndamento, $concretagem->situacao());
        $this->assertSame([], $concretagem->cargas());
        $this->assertAproximado(0.0, $concretagem->volumeAceitoEmM3());
    }

    #[Test]
    public function recusa_data_futura(): void
    {
        $this->recusa(
            fn () => new Concretagem(
                'OBR-1',
                $this->lajeDeTeste(),
                new DateTimeImmutable('2026-03-11'),
                'Usina',
                'Responsável',
                new DateTimeImmutable('2026-03-10'),
            ),
            'a data ainda não chegou',
        );
    }

    // ---------- Concretagem: recebimento de cargas ----------

    #[Test]
    public function aceita_carga_com_abatimento_na_faixa_e_transporte_no_tempo(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $carga = $this->chegaCarga($concretagem, 100, '08:00', '08:50');

        $this->assertTrue($carga->foiAceita(), 'aceita');
        $this->assertSame(null, $carga->devolucao);
        $this->assertSame(50, $carga->tempoDeTransporteEmMinutos());
    }

    #[Test]
    public function devolve_carga_com_abatimento_fora_da_faixa(): void
    {
        // Laje especificada em 100 ± 20: 130 está fora.
        $concretagem = $this->concretagemDeTeste();

        $carga = $this->chegaCarga($concretagem, 130);

        $this->assertTrue($carga->foiDevolvida(), 'devolvida');
        $this->assertSame(MotivoDeDevolucao::AbatimentoForaDaFaixa, $carga->devolucao);
    }

    #[Test]
    public function aceita_abatimento_exatamente_no_limite(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->assertTrue($this->chegaCarga($concretagem, 80, notaFiscal: 'NF-1')->foiAceita(), 'limite inferior');
        $this->assertTrue($this->chegaCarga($concretagem, 120, notaFiscal: 'NF-2')->foiAceita(), 'limite superior');
    }

    #[Test]
    public function devolve_carga_que_passou_do_tempo_de_transporte(): void
    {
        // Saiu 8h, chegou 10h45: 165 minutos, acima dos 150 da NBR 7212.
        $concretagem = $this->concretagemDeTeste();

        $carga = $this->chegaCarga($concretagem, 100, '08:00', '10:45');

        $this->assertTrue($carga->foiDevolvida(), 'devolvida');
        $this->assertSame(MotivoDeDevolucao::TempoDeTransporteExcedido, $carga->devolucao);
        $this->assertSame(165, $carga->tempoDeTransporteEmMinutos());
    }

    #[Test]
    public function aceita_carga_exatamente_nos_150_minutos(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->assertTrue($this->chegaCarga($concretagem, 100, '08:00', '10:30')->foiAceita(), '150 min é o limite, não além');
    }

    #[Test]
    public function o_tempo_e_julgado_antes_do_abatimento(): void
    {
        // Carga atrasada E com abatimento errado: o motivo registrado é o tempo,
        // porque é a primeira coisa que o canteiro confere ao caminhão chegar.
        $concretagem = $this->concretagemDeTeste();

        $carga = $this->chegaCarga($concretagem, 130, '08:00', '11:00');

        $this->assertSame(MotivoDeDevolucao::TempoDeTransporteExcedido, $carga->devolucao);
    }

    #[Test]
    public function numera_as_cargas_em_sequencia_contando_as_devolvidas(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
        $this->chegaCarga($concretagem, 140, notaFiscal: 'NF-2');
        $terceira = $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-3');

        $this->assertSame(3, $terceira->numero, 'a devolvida ocupa o número 2');
        $this->assertSame(3, count($concretagem->cargas()));
    }

    #[Test]
    public function so_o_volume_aceito_conta_como_concretado(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->chegaCarga($concretagem, 100, volume: 8.0, notaFiscal: 'NF-1');
        $this->chegaCarga($concretagem, 140, volume: 8.0, notaFiscal: 'NF-2');
        $this->chegaCarga($concretagem, 95, volume: 7.5, notaFiscal: 'NF-3');

        $this->assertAproximado(15.5, $concretagem->volumeAceitoEmM3());
        $this->assertAproximado(8.0, $concretagem->volumeDevolvidoEmM3());
        $this->assertSame(2, count($concretagem->cargasAceitas()));
        $this->assertSame(1, count($concretagem->cargasDevolvidas()));
    }

    #[Test]
    public function recusa_carga_que_chegou_em_outro_dia(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->recusa(
            fn () => $concretagem->receberCarga(
                'NF-9',
                null,
                8.0,
                new DateTimeImmutable('2026-03-11 08:00'),
                new DateTimeImmutable('2026-03-11 08:50'),
                100,
            ),
            'concretagem do dia certo',
        );
    }

    #[Test]
    public function recusa_chegada_anterior_a_saida_da_usina(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $this->recusa(
            fn () => $this->chegaCarga($concretagem, 100, '09:00', '08:50'),
            'não pode chegar antes de sair',
        );
    }

    #[Test]
    public function normaliza_a_placa_e_guarda_a_observacao(): void
    {
        $concretagem = $this->concretagemDeTeste();

        $carga = $concretagem->receberCarga('NF-1', ' abc1d23 ', 8.0, $this->hora('08:00'), $this->hora('08:50'), 100, '  Bombeado  ');

        $this->assertSame('ABC1D23', $carga->placa);
        $this->assertSame('Bombeado', $carga->observacao);
    }

    // ---------- Concretagem: encerramento ----------

    #[Test]
    public function conclui_com_carga_aceita_e_exemplar_de_28_dias(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);
        $concretagem->moldar(1, $this->hora('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);

        $concretagem->concluir();

        $this->assertTrue($concretagem->estaConcluida(), 'concluída');
    }

    #[Test]
    public function nao_conclui_sem_exemplar_de_28_dias(): void
    {
        // Carga aceita, mas só moldou 7 dias: o lote nunca poderia ser aceito.
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);
        $concretagem->moldar(1, $this->hora('09:00'), [IdadeDeEnsaio::SeteDias]);

        $this->recusa(fn () => $concretagem->concluir(), 'Nenhum exemplar de 28 dias');
    }

    #[Test]
    public function nao_conclui_sem_carga_aceita(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 140);

        $this->recusa(fn () => $concretagem->concluir(), 'Não há carga aceita');
    }

    #[Test]
    public function concluida_nao_recebe_mais_carga(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);
        $concretagem->moldar(1, $this->hora('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);
        $concretagem->concluir();

        $this->recusa(fn () => $this->chegaCarga($concretagem, 100, notaFiscal: 'NF-2'), 'não recebe mais cargas');
    }

    #[Test]
    public function cancela_enquanto_nada_entrou_na_forma(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 140);

        $concretagem->cancelar();

        $this->assertSame(SituacaoDaConcretagem::Cancelada, $concretagem->situacao());
    }

    #[Test]
    public function nao_cancela_depois_que_o_concreto_foi_lancado(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);

        $this->recusa(fn () => $concretagem->cancelar(), 'não se cancela');
    }

    #[Test]
    public function nao_conclui_duas_vezes(): void
    {
        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100);
        $concretagem->moldar(1, $this->hora('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);
        $concretagem->concluir();

        $this->recusa(fn () => $concretagem->concluir(), 'não é possível concluir');
    }
}
