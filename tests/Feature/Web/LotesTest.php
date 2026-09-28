<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\Obra\RepositorioDeObras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\TestCase;

/*
 * Formar o lote, julgá-lo e ver a conta.
 *
 * As datas aqui são fixas (10/03/2026) porque o lote não depende do relógio:
 * ele depende dos resultados dos exemplares de 28 dias, que já estão no
 * banco. Quem depende do relógio é a agenda, e ela tem o teste dela.
 */
final class LotesTest extends TestCase
{
    use ObjetosDeExemplo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->pilaresDeTeste());
    }

    /**
     * Grava uma concretagem concluída com um exemplar de 28 dias por
     * resistência pedida. Nulo deixa o exemplar aguardando a prensa.
     *
     * @param array<int, ?float> $resistenciasEmMPa
     */
    private function concluida(int $numero, array $resistenciasEmMPa, string $data = '2026-03-10', ?object $elemento = null): Concretagem
    {
        $concretagem = $this->concretagemComResultados($numero, $resistenciasEmMPa, $elemento, $data);

        app(RepositorioDeConcretagens::class)->salvar($concretagem);

        return $concretagem;
    }

    private function formar(array $numeros, string $amostragem = 'total', string $condicao = 'a'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/obras/OBR-2026-007/lotes', [
            'concretagens' => array_map('strval', $numeros),
            'condicao' => $condicao,
            'amostragem' => $amostragem,
        ]);
    }

    // ---------- formar ----------

    #[Test]
    public function o_formulario_lista_so_concretagem_concluida_fora_de_lote(): void
    {
        $this->concluida(1, [32.0]);

        $this->get('/obras/OBR-2026-007/lotes/novo')
            ->assertOk()
            ->assertSee('Formar lote de aceitação')
            ->assertSee('nº 1')
            ->assertSee('C30');

        $this->formar([1])->assertRedirect('/obras/OBR-2026-007/lotes/1');

        // Depois de entrar no lote, a concretagem sai da lista.
        $this->get('/obras/OBR-2026-007/lotes/novo')
            ->assertOk()
            ->assertSee('Nenhuma concretagem concluída fora de lote');
    }

    #[Test]
    public function forma_o_lote_com_a_classe_da_primeira_concretagem(): void
    {
        $this->concluida(1, [32.0]);
        $this->concluida(2, [33.0], '2026-03-11');

        $this->formar([1, 2])
            ->assertRedirect('/obras/OBR-2026-007/lotes/1')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, '2 concretagem(ns)'));

        $lote = app(RepositorioDeLotes::class)->porNumero('OBR-2026-007', 1);

        // A classe não foi digitada: saiu do elemento da primeira concretagem.
        $this->assertEqualsWithDelta(30.0, $lote?->classe->fck() ?? 0.0, 0.001);
        $this->assertCount(2, $lote?->concretagens() ?? []);
    }

    #[Test]
    public function o_dominio_recusa_juntar_classes_diferentes(): void
    {
        $this->concluida(1, [32.0]);
        $this->concluida(2, [38.0], '2026-03-11', $this->pilaresDeTeste());

        $this->formar([1, 2])
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'Um lote tem um fck só'));

        $this->assertDatabaseCount('lotes', 0);
    }

    #[Test]
    public function o_dominio_recusa_mais_de_tres_dias_de_concretagem(): void
    {
        $this->concluida(1, [32.0], '2026-03-10');
        $this->concluida(2, [32.0], '2026-03-11');
        $this->concluida(3, [32.0], '2026-03-12');
        $this->concluida(4, [32.0], '2026-03-13');

        $this->formar([1, 2, 3, 4])
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, '3 dias de concretagem'));
    }

    #[Test]
    public function uma_concretagem_entra_em_um_lote_so(): void
    {
        $this->concluida(1, [32.0]);

        $this->formar([1]);
        $this->formar([1])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'já está no lote 1'));

        $this->assertDatabaseCount('lotes', 1);
    }

    #[Test]
    public function o_form_request_recusa_lote_sem_concretagem(): void
    {
        $this->post('/obras/OBR-2026-007/lotes', ['condicao' => 'a', 'amostragem' => 'total'])
            ->assertSessionHasErrors('concretagens');
    }

    #[Test]
    public function o_form_request_recusa_condicao_e_amostragem_invalidas(): void
    {
        $this->concluida(1, [32.0]);

        $this->post('/obras/OBR-2026-007/lotes', [
            'concretagens' => ['1'],
            'condicao' => 'z',
            'amostragem' => 'por_amostra',
        ])->assertSessionHasErrors(['condicao', 'amostragem']);
    }

    // ---------- julgar ----------

    #[Test]
    public function julga_e_aceita_o_lote_que_atende_ao_projeto(): void
    {
        $this->concluida(1, [32.4]);
        $this->formar([1]);

        $this->post('/obras/OBR-2026-007/lotes/1/julgar')
            ->assertRedirect('/obras/OBR-2026-007/lotes/1')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'Aceito'));

        $lote = app(RepositorioDeLotes::class)->porNumero('OBR-2026-007', 1);

        $this->assertTrue($lote?->foiAceito());
        $this->assertNull(app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1));
    }

    #[Test]
    public function o_lote_reprovado_abre_a_nao_conformidade_sozinho(): void
    {
        // Amostragem total: o fck estimado é o menor exemplar — 24,0 contra 30.
        $this->concluida(1, [24.0, 31.0]);
        $this->formar([1]);

        $this->post('/obras/OBR-2026-007/lotes/1/julgar')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'não conformidade foi aberta'));

        $lote = app(RepositorioDeLotes::class)->porNumero('OBR-2026-007', 1);
        $naoConformidade = app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1);

        $this->assertFalse($lote?->foiAceito());
        /*
         * Não existe lote reprovado sem tratamento aberto: a não conformidade
         * nasce na mesma transação do julgamento. É a regra que impede o
         * resultado ruim de ficar esquecido numa tabela.
         */
        $this->assertNotNull($naoConformidade);
        $this->assertEqualsWithDelta(24.0, $naoConformidade?->fckEstimadoEmMPa ?? 0.0, 0.05);
    }

    #[Test]
    public function o_dominio_recusa_julgar_com_exemplar_pendente(): void
    {
        // O segundo exemplar não foi rompido.
        $this->concluida(1, [32.0, null]);
        $this->formar([1]);

        $this->post('/obras/OBR-2026-007/lotes/1/julgar')
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'aguardando rompimento'));

        $this->assertFalse(app(RepositorioDeLotes::class)->porNumero('OBR-2026-007', 1)?->situacao()->foiJulgado());
    }

    #[Test]
    public function o_dominio_recusa_julgar_duas_vezes(): void
    {
        $this->concluida(1, [32.4]);
        $this->formar([1]);
        $this->post('/obras/OBR-2026-007/lotes/1/julgar');

        $this->post('/obras/OBR-2026-007/lotes/1/julgar')
            ->assertSessionHas('erro');
    }

    #[Test]
    public function amostragem_parcial_exige_seis_exemplares(): void
    {
        $this->concluida(1, [32.0, 33.0]);

        $this->formar([1], 'parcial');

        $this->post('/obras/OBR-2026-007/lotes/1/julgar')
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'ao menos 6 exemplares'));
    }

    // ---------- a memória de cálculo ----------

    #[Test]
    public function a_tela_do_lote_julgado_mostra_a_conta_inteira(): void
    {
        $this->concluida(1, [24.0, 31.0]);
        $this->formar([1]);
        $this->post('/obras/OBR-2026-007/lotes/1/julgar');

        $this->get('/obras/OBR-2026-007/lotes/1')
            ->assertOk()
            ->assertSee('Memória de cálculo')
            ->assertSee('Amostragem total com n ≤ 20: fck,est é o menor exemplar (f1).')
            ->assertSee('24,0 MPa')
            ->assertSee('30,0 MPa')
            ->assertSee('Não conforme')
            // O rótulo do botão quebra em duas linhas no template; o que
            // importa é que ele leva à não conformidade recém-aberta.
            ->assertSee('/lotes/1/nao-conformidade', false);
    }

    #[Test]
    public function a_memoria_da_amostragem_parcial_mostra_a_formula_e_o_piso(): void
    {
        // Seis exemplares: a fórmula da norma entra, e o piso ψ6 × f1 aparece.
        $this->concluida(1, [28.0, 29.0, 30.0, 31.0, 32.0, 33.0]);
        $this->formar([1], 'parcial');
        $this->post('/obras/OBR-2026-007/lotes/1/julgar');

        $this->get('/obras/OBR-2026-007/lotes/1')
            ->assertOk()
            ->assertSee('Fórmula da norma')
            ->assertSee('Piso ψ6 × f1')
            ->assertSee('tabela de ψ6');
    }

    #[Test]
    public function o_lote_aberto_nao_mostra_memoria_e_avisa_o_que_falta(): void
    {
        $this->concluida(1, [32.0, null]);
        $this->formar([1]);

        $this->get('/obras/OBR-2026-007/lotes/1')
            ->assertOk()
            ->assertSee('Aberto')
            ->assertSee('Aguardando:')
            ->assertDontSee('Memória de cálculo');
    }

    #[Test]
    public function lote_inexistente_da_404(): void
    {
        $this->get('/obras/OBR-2026-007/lotes/99')->assertNotFound();
        $this->get('/obras/NAO-EXISTE/lotes/novo')->assertNotFound();
    }

    #[Test]
    public function a_pagina_da_obra_lista_os_lotes(): void
    {
        $this->concluida(1, [32.4]);
        $this->formar([1]);
        $this->post('/obras/OBR-2026-007/lotes/1/julgar');

        $this->get('/obras/OBR-2026-007')
            ->assertOk()
            ->assertSee('Lotes de aceitação')
            ->assertSee('32,4 MPa')
            ->assertSee('Aceito');
    }
}
