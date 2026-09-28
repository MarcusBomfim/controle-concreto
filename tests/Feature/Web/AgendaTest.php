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
 * A agenda é a única tela que depende do relógio da máquina: o controlador
 * pergunta "o que está na janela agora". Datas fixas quebrariam o teste no
 * dia seguinte, então tudo aqui é relativo a hoje.
 *
 * A idade de 91 dias faz o trabalho pesado: a tolerância dela é de 48 h, e
 * um corpo de prova moldado há exatamente 91 dias ao meio-dia está dentro da
 * janela a qualquer hora que o teste rode. Com a idade de 7 dias — 6 h de
 * tolerância — o teste passaria de manhã e falharia à noite.
 */
final class AgendaTest extends TestCase
{
    use ObjetosDeExemplo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());
    }

    /**
     * Concretagem de N dias atrás, com uma carga aceita e um exemplar por
     * idade, tudo moldado ao meio-dia daquele dia.
     */
    private function moldada(int $diasAtras, IdadeDeEnsaio ...$idades): Concretagem
    {
        $dia = (new DateTimeImmutable('today'))->modify(sprintf('-%d days', $diasAtras));
        $emQue = static fn (string $hora): DateTimeImmutable => new DateTimeImmutable($dia->format('Y-m-d') . ' ' . $hora);

        $concretagem = new Concretagem('OBR-2026-007', $this->lajeDeTeste(), $dia, 'Usina', 'Marcus', $dia);
        $concretagem->receberCarga('NF-' . $diasAtras, null, 8.0, $emQue('11:00'), $emQue('11:40'), 100);
        $concretagem->moldar(1, $emQue('12:00'), $idades);

        $repositorio = app(RepositorioDeConcretagens::class);
        $concretagem->definirNumero($repositorio->salvar($concretagem));

        return $concretagem;
    }

    private function identificacao(Concretagem $concretagem): string
    {
        return $concretagem->exemplares()[0]->primeiro->identificacao;
    }

    // ---------- a tela ----------

    #[Test]
    public function a_agenda_abre_na_raiz(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Agenda do laboratório');
    }

    #[Test]
    public function a_agenda_vazia_diz_que_nao_ha_nada_na_janela(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Nenhum corpo de prova com a janela aberta neste momento.')
            ->assertSee('Nada previsto para os próximos 7 dias.');
    }

    #[Test]
    public function lista_o_corpo_de_prova_com_a_janela_aberta_agora(): void
    {
        $concretagem = $this->moldada(91, IdadeDeEnsaio::NoventaEUmDias);

        $this->get('/')
            ->assertOk()
            ->assertSee('Na janela — romper agora')
            ->assertSee($this->identificacao($concretagem))
            ->assertSee('L3-P4')
            // O formulário de rompimento só aparece para quem pode romper.
            ->assertSee('name="carga_kn"', false);
    }

    #[Test]
    public function lista_o_vencido_sem_oferecer_o_rompimento(): void
    {
        $concretagem = $this->moldada(30, IdadeDeEnsaio::SeteDias);

        $resposta = $this->get('/')
            ->assertOk()
            ->assertSee('Vencidos — perderam a idade')
            ->assertSee($this->identificacao($concretagem))
            ->assertSee('Passou da janela de rompimento sem ser rompido');

        // Um só corpo de prova, vencido: nenhum formulário de kN na página.
        $this->assertStringNotContainsString('name="carga_kn"', $resposta->getContent() ?: '');
    }

    #[Test]
    public function lista_o_que_rompe_nos_proximos_dias(): void
    {
        $concretagem = $this->moldada(25, IdadeDeEnsaio::VinteEOitoDias);

        // Moldado há 25 dias ao meio-dia, idade de 28: rompe daqui a 3 dias.
        $previsto = (new DateTimeImmutable('today'))->modify('+3 days')->format('d/m/Y') . ' 12:00';

        $this->get('/')
            ->assertOk()
            ->assertSee('Próximos 7 dias')
            ->assertSee($this->identificacao($concretagem))
            ->assertSee($previsto);
    }

    // ---------- lançar o resultado da prensa ----------

    #[Test]
    public function registra_o_rompimento_direto_da_agenda(): void
    {
        $concretagem = $this->moldada(91, IdadeDeEnsaio::NoventaEUmDias);
        $identificacao = $this->identificacao($concretagem);

        // 245,5 kN num cilindro de 10 cm dão 31,3 MPa.
        $this->post("/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$identificacao}/romper", [
            'carga_kn' => '245,5',
            'diametro_mm' => '100',
            'rompido_em' => (new DateTimeImmutable('now'))->format('Y-m-d\TH:i'),
            'voltar' => '/',
        ])
            ->assertRedirect('/')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, $identificacao));

        $gravada = app(RepositorioDeConcretagens::class)->porNumero('OBR-2026-007', 1);
        $corpoDeProva = $gravada?->corpoDeProva($identificacao);

        $this->assertTrue($corpoDeProva?->foiRompido());
        $this->assertEqualsWithDelta(31.3, $corpoDeProva?->resistenciaEmMPa() ?? 0.0, 0.05);
    }

    #[Test]
    public function o_dominio_recusa_o_rompimento_fora_da_janela(): void
    {
        $concretagem = $this->moldada(30, IdadeDeEnsaio::SeteDias);
        $identificacao = $this->identificacao($concretagem);

        $this->post("/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$identificacao}/romper", [
            'carga_kn' => '200',
            'diametro_mm' => '100',
            'rompido_em' => (new DateTimeImmutable('now'))->format('Y-m-d\TH:i'),
        ])
            ->assertRedirect('/')
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'fora da janela'));

        $gravada = app(RepositorioDeConcretagens::class)->porNumero('OBR-2026-007', 1);

        $this->assertFalse($gravada?->corpoDeProva($identificacao)?->foiRompido());
    }

    #[Test]
    public function o_form_request_recusa_carga_e_diametro_invalidos(): void
    {
        $concretagem = $this->moldada(91, IdadeDeEnsaio::NoventaEUmDias);
        $identificacao = $this->identificacao($concretagem);

        $this->post("/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$identificacao}/romper", [
            'carga_kn' => '0',
            // 120 mm não existe: a NBR 5738 prevê 100 ou 150.
            'diametro_mm' => '120',
            'rompido_em' => 'ontem de manhã',
        ])->assertSessionHasErrors(['carga_kn', 'diametro_mm', 'rompido_em']);
    }

    // ---------- descartar ----------

    #[Test]
    public function descarta_o_vencido_com_o_motivo(): void
    {
        $concretagem = $this->moldada(30, IdadeDeEnsaio::SeteDias);
        $identificacao = $this->identificacao($concretagem);

        $this->post("/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$identificacao}/descartar", [
            'motivo' => 'Passou da janela de rompimento sem ser rompido',
            'voltar' => '/',
        ])
            ->assertRedirect('/')
            ->assertSessionHas('mensagem');

        $gravada = app(RepositorioDeConcretagens::class)->porNumero('OBR-2026-007', 1);

        $this->assertSame(
            'Passou da janela de rompimento sem ser rompido',
            $gravada?->corpoDeProva($identificacao)?->motivoDoDescarte(),
        );
    }

    #[Test]
    public function o_form_request_exige_o_motivo_do_descarte(): void
    {
        $concretagem = $this->moldada(30, IdadeDeEnsaio::SeteDias);

        $this->post(
            "/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$this->identificacao($concretagem)}/descartar",
            ['motivo' => ''],
        )->assertSessionHasErrors('motivo');
    }

    #[Test]
    public function corpo_de_prova_inexistente_nao_derruba_a_tela(): void
    {
        $this->moldada(30, IdadeDeEnsaio::SeteDias);

        $this->post('/obras/OBR-2026-007/concretagens/1/corpos-de-prova/NAO-EXISTE/descartar', [
            'motivo' => 'Qualquer',
        ])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'Não existe corpo de prova'));
    }

    // ---------- o campo "voltar" ----------

    #[Test]
    public function nao_redireciona_para_fora_do_dominio(): void
    {
        $concretagem = $this->moldada(30, IdadeDeEnsaio::SeteDias);

        /*
         * O campo "voltar" vem do formulário, e o formulário vem do
         * navegador: quem manda o POST manda o que quiser. Um destino
         * absoluto seria redirecionamento aberto — a pessoa clica em
         * "Confirmar descarte" aqui e o navegador a larga em outro site.
         */
        foreach (['https://exemplo.invalido/phishing', '//exemplo.invalido'] as $malicioso) {
            $this->post(
                "/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$this->identificacao($concretagem)}/descartar",
                ['motivo' => 'Qualquer', 'voltar' => $malicioso],
            )->assertRedirect('/');
        }
    }

    #[Test]
    public function volta_para_a_tela_da_concretagem_quando_o_post_vem_de_la(): void
    {
        $concretagem = $this->moldada(30, IdadeDeEnsaio::SeteDias);

        $this->post(
            "/obras/OBR-2026-007/concretagens/1/corpos-de-prova/{$this->identificacao($concretagem)}/descartar",
            ['motivo' => 'Quebrou na desforma', 'voltar' => '/obras/OBR-2026-007/concretagens/1'],
        )->assertRedirect('/obras/OBR-2026-007/concretagens/1');
    }
}
