<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Obra\RepositorioDeObras;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\TestCase;

/*
 * A tela do dia de concretagem, de ponta a ponta: abrir, receber caminhão,
 * moldar, concluir.
 *
 * A data é sempre hoje, porque o domínio exige que a carga chegue no dia da
 * concretagem. As horas são fixas — o formulário manda só HH:MM, e a data
 * vem da concretagem.
 */
final class ConcretagensTest extends TestCase
{
    use ObjetosDeExemplo;
    use RefreshDatabase;

    private string $hoje;

    protected function setUp(): void
    {
        parent::setUp();

        $this->hoje = (new DateTimeImmutable('today'))->format('Y-m-d');

        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());
    }

    /** Uma concretagem de hoje, já gravada, pronta para receber caminhão. */
    private function abertaHoje(): Concretagem
    {
        $hoje = new DateTimeImmutable('today');

        $concretagem = new Concretagem('OBR-2026-007', $this->lajeDeTeste(), $hoje, 'Usina', 'Marcus', $hoje);
        $concretagem->definirNumero(app(RepositorioDeConcretagens::class)->salvar($concretagem));

        return $concretagem;
    }

    private function recarregar(): ?Concretagem
    {
        return app(RepositorioDeConcretagens::class)->porNumero('OBR-2026-007', 1);
    }

    // ---------- abrir ----------

    #[Test]
    public function abre_a_concretagem_pelo_formulario(): void
    {
        $this->get('/obras/OBR-2026-007/concretagens/nova')
            ->assertOk()
            ->assertSee('Nova concretagem')
            ->assertSee('L3-P4');

        $this->post('/obras/OBR-2026-007/concretagens', [
            'elemento' => 'L3-P4',
            'data' => $this->hoje,
            'fornecedor' => 'Concreteira Litoral',
            'responsavel' => 'Marcus Bomfim',
        ])
            ->assertRedirect('/obras/OBR-2026-007/concretagens/1')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'nº 1 aberta'));

        $this->assertSame('Concreteira Litoral', $this->recarregar()?->fornecedor);
    }

    #[Test]
    public function o_form_request_recusa_data_no_futuro(): void
    {
        $this->post('/obras/OBR-2026-007/concretagens', [
            'elemento' => 'L3-P4',
            'data' => (new DateTimeImmutable('+2 days'))->format('Y-m-d'),
            'fornecedor' => 'Usina',
            'responsavel' => 'Marcus',
        ])->assertSessionHasErrors('data');

        $this->assertDatabaseCount('concretagens', 0);
    }

    #[Test]
    public function o_dominio_recusa_elemento_que_nao_existe_na_obra(): void
    {
        $this->post('/obras/OBR-2026-007/concretagens', [
            'elemento' => 'NAO-EXISTE',
            'data' => $this->hoje,
            'fornecedor' => 'Usina',
            'responsavel' => 'Marcus',
        ])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'não existe na obra'));
    }

    // ---------- receber carga ----------

    #[Test]
    public function registra_a_carga_dentro_da_especificacao(): void
    {
        $this->abertaHoje();

        $this->post('/obras/OBR-2026-007/concretagens/1/cargas', [
            'nota_fiscal' => 'NF-9001',
            'placa' => 'abc-1d23',
            // Vírgula: prepareForValidation converte antes das regras.
            'volume_m3' => '8,5',
            'saida' => '08:00',
            'chegada' => '08:40',
            'abatimento_mm' => 100,
            'observacao' => 'Bomba lança',
        ])
            ->assertRedirect('/obras/OBR-2026-007/concretagens/1')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'aceita'));

        $carga = $this->recarregar()?->carga(1);

        $this->assertTrue($carga?->foiAceita());
        $this->assertSame('ABC-1D23', $carga?->placa, 'a entidade normalizou a placa');
        $this->assertSame(40, $carga?->tempoDeTransporteEmMinutos());
    }

    #[Test]
    public function devolve_o_caminhao_com_abatimento_fora_da_faixa(): void
    {
        $this->abertaHoje();

        // A laje é 100 ± 20 mm: 150 reprova no cone.
        $this->post('/obras/OBR-2026-007/concretagens/1/cargas', [
            'nota_fiscal' => 'NF-9002',
            'volume_m3' => '8,0',
            'saida' => '08:00',
            'chegada' => '08:40',
            'abatimento_mm' => 150,
        ])->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'DEVOLVIDA'));

        $concretagem = $this->recarregar();

        $this->assertTrue($concretagem?->carga(1)?->foiDevolvida());
        $this->assertEqualsWithDelta(0.0, $concretagem?->volumeAceitoEmM3() ?? -1.0, 0.001);
    }

    #[Test]
    public function devolve_o_caminhao_que_demorou_demais(): void
    {
        $this->abertaHoje();

        // 150 min é o limite; 08:00 às 11:00 são 180.
        $this->post('/obras/OBR-2026-007/concretagens/1/cargas', [
            'nota_fiscal' => 'NF-9003',
            'volume_m3' => '8,0',
            'saida' => '08:00',
            'chegada' => '11:00',
            'abatimento_mm' => 100,
        ])->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'DEVOLVIDA'));
    }

    #[Test]
    public function o_form_request_recusa_hora_fora_do_formato(): void
    {
        $this->abertaHoje();

        $this->post('/obras/OBR-2026-007/concretagens/1/cargas', [
            'nota_fiscal' => 'NF-9004',
            'volume_m3' => '8,0',
            'saida' => 'de manhã',
            'chegada' => '25:99',
            'abatimento_mm' => 100,
        ])->assertSessionHasErrors(['saida', 'chegada']);

        $this->assertDatabaseCount('cargas', 0);
    }

    // ---------- moldar ----------

    #[Test]
    public function molda_um_exemplar_por_idade_marcada(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        $this->post('/obras/OBR-2026-007/concretagens/1/moldagens', [
            'carga' => 1,
            'hora' => '09:00',
            'idades' => ['7', '28'],
        ])->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, '2 exemplar(es)'));

        $concretagem = $this->recarregar();

        $this->assertCount(2, $concretagem?->exemplares() ?? []);
        // Dois cilindros por exemplar: é o que a NBR 5738 chama de exemplar.
        $this->assertCount(4, $concretagem?->corposDeProva() ?? []);
    }

    #[Test]
    public function o_form_request_recusa_moldagem_sem_idade(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        $this->post('/obras/OBR-2026-007/concretagens/1/moldagens', [
            'carga' => 1,
            'hora' => '09:00',
        ])->assertSessionHasErrors('idades');
    }

    #[Test]
    public function o_form_request_recusa_idade_que_nao_existe(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        // A NBR prevê 1, 3, 7, 28, 63 e 91 dias — 14 não é uma delas.
        $this->post('/obras/OBR-2026-007/concretagens/1/moldagens', [
            'carga' => 1,
            'hora' => '09:00',
            'idades' => ['14'],
        ])->assertSessionHasErrors('idades.0');
    }

    #[Test]
    public function o_dominio_recusa_moldar_antes_da_chegada_da_carga(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        $this->post('/obras/OBR-2026-007/concretagens/1/moldagens', [
            'carga' => 1,
            'hora' => '07:00',
            'idades' => ['28'],
        ])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'anterior à chegada da carga'));
    }

    // ---------- concluir e cancelar ----------

    #[Test]
    public function o_dominio_recusa_concluir_sem_exemplar_de_28_dias(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        $this->post('/obras/OBR-2026-007/concretagens/1/moldagens', [
            'carga' => 1,
            'hora' => '09:00',
            'idades' => ['7'],
        ]);

        $this->post('/obras/OBR-2026-007/concretagens/1/concluir')
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'exemplar de 28 dias'));

        $this->assertTrue($this->recarregar()?->situacao()->aceitaCarga());
    }

    #[Test]
    public function conclui_a_concretagem(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        $this->post('/obras/OBR-2026-007/concretagens/1/moldagens', [
            'carga' => 1,
            'hora' => '09:00',
            'idades' => ['7', '28'],
        ]);

        $this->post('/obras/OBR-2026-007/concretagens/1/concluir')
            ->assertRedirect('/obras/OBR-2026-007/concretagens/1')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'concluída'));

        $this->assertTrue($this->recarregar()?->estaConcluida());
    }

    #[Test]
    public function cancela_a_concretagem_que_nao_recebeu_concreto(): void
    {
        $this->abertaHoje();

        $this->post('/obras/OBR-2026-007/concretagens/1/cancelar')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'cancelada'));

        $this->assertFalse($this->recarregar()?->situacao()->aceitaCarga());
    }

    #[Test]
    public function o_dominio_recusa_cancelar_com_concreto_na_forma(): void
    {
        $this->abertaHoje();
        $this->cargaAceita();

        $this->post('/obras/OBR-2026-007/concretagens/1/cancelar')
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'não se cancela'));
    }

    // ---------- a tela ----------

    #[Test]
    public function a_tela_mostra_as_cargas_e_os_corpos_de_prova(): void
    {
        $concretagem = $this->abertaHoje();
        $concretagem->receberCarga('NF-7001', 'ABC-1D23', 8.0, $this->horaDeHoje('08:00'), $this->horaDeHoje('08:40'), 100);
        $concretagem->moldar(1, $this->horaDeHoje('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);
        app(RepositorioDeConcretagens::class)->salvar($concretagem);

        $this->get('/obras/OBR-2026-007/concretagens/1')
            ->assertOk()
            ->assertSee('Concretagem nº 1')
            ->assertSee('NF-7001')
            ->assertSee('8,0 m³')
            ->assertSee('40 min')
            ->assertSee('Aceita')
            ->assertSee('Receber caminhão')
            ->assertSee('Moldar corpos de prova')
            // Dois cilindros do exemplar de 28 dias da carga 1.
            ->assertSee('C1-28d-A')
            ->assertSee('C1-28d-B');
    }

    #[Test]
    public function a_concretagem_concluida_nao_mostra_os_formularios(): void
    {
        $concretagem = $this->abertaHoje();
        $concretagem->receberCarga('NF-7002', null, 8.0, $this->horaDeHoje('08:00'), $this->horaDeHoje('08:40'), 100);
        $concretagem->moldar(1, $this->horaDeHoje('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);
        $concretagem->concluir();
        app(RepositorioDeConcretagens::class)->salvar($concretagem);

        $this->get('/obras/OBR-2026-007/concretagens/1')
            ->assertOk()
            ->assertSee('Concluída')
            ->assertDontSee('Receber caminhão');
    }

    #[Test]
    public function concretagem_inexistente_da_404(): void
    {
        $this->get('/obras/OBR-2026-007/concretagens/99')->assertNotFound();
        $this->get('/obras/NAO-EXISTE/concretagens/nova')->assertNotFound();
    }

    #[Test]
    public function numero_que_nao_e_numero_da_404(): void
    {
        $this->abertaHoje();

        /*
         * whereNumber na rota. Sem ele o roteador casaria /concretagens/abc,
         * o controlador receberia (int) 'abc' — zero — e a tela diria
         * "não existe concretagem 0" em vez de 404.
         */
        $this->get('/obras/OBR-2026-007/concretagens/abc')->assertNotFound();
    }

    /** Uma carga aceita na concretagem 1, pela tela. */
    private function cargaAceita(): void
    {
        $this->post('/obras/OBR-2026-007/concretagens/1/cargas', [
            'nota_fiscal' => 'NF-8000',
            'volume_m3' => '8,0',
            'saida' => '08:00',
            'chegada' => '08:40',
            'abatimento_mm' => 100,
        ]);
    }

    /** Um horário no dia de hoje — a trait ObjetosDeExemplo fixa o dela em 2026. */
    protected function horaDeHoje(string $horario): DateTimeImmutable
    {
        return new DateTimeImmutable($this->hoje . ' ' . $horario);
    }
}
