<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Obra\RepositorioDeObras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\Autenticacao;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\TestCase;

/*
 * Testes HTTP: sobem a aplicação inteira, atravessam roteador, controlador,
 * Form Request e Blade, e conferem a resposta.
 *
 * Na versão em PHP puro isto exigia montar uma Requisicao à mão e inspecionar
 * o corpo da Resposta com str_contains. Aqui o $this->get() devolve um objeto
 * que sabe afirmar sobre status, conteúdo, redirecionamento e sessão.
 */
final class ObrasTest extends TestCase
{
    use Autenticacao;
    use ObjetosDeExemplo;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comoEngenheiro();
    }

    private function comObraGravada(): void
    {
        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());
    }

    // ---------- listagem ----------

    #[Test]
    public function a_lista_de_obras_abre_em_obras(): void
    {
        // A raiz é a agenda do laboratório desde a Parte 4.
        $this->get('/obras')->assertOk();
    }

    #[Test]
    public function a_lista_vazia_explica_o_que_fazer(): void
    {
        $this->get('/obras')
            ->assertOk()
            ->assertSee('Nenhuma obra cadastrada');
    }

    #[Test]
    public function a_lista_mostra_a_obra_com_os_contadores(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem);
        app(RepositorioDeConcretagens::class)->salvar($concretagem);

        $this->get('/obras')
            ->assertOk()
            ->assertSee('Edifício Vista Serra')
            ->assertSee('OBR-2026-007')
            ->assertSee('1 em andamento');
    }

    // ---------- cadastro de obra ----------

    #[Test]
    public function cadastra_a_obra_pelo_formulario(): void
    {
        $this->get('/obras/nova')->assertOk()->assertSee('Cadastrar obra');

        $this->post('/obras', [
            'codigo' => 'obr-2026-050',
            'nome' => 'Terminal de granéis',
            'cliente' => 'Porto de Santos',
            'responsavel_tecnico' => 'Marcus Bomfim',
            'registro_profissional' => 'CREA-SP 5069874521/D',
        ])
            ->assertRedirect('/obras/OBR-2026-050')
            ->assertSessionHas('mensagem');

        $obra = app(RepositorioDeObras::class)->porCodigo('OBR-2026-050');

        $this->assertSame('Terminal de granéis', $obra?->nome);
        $this->assertSame('OBR-2026-050', $obra?->codigo, 'o domínio normalizou para maiúsculas');
    }

    #[Test]
    public function o_form_request_recusa_campo_faltando(): void
    {
        $this->post('/obras', ['codigo' => 'OBR-1'])
            ->assertSessionHasErrors(['nome', 'cliente', 'responsavel_tecnico', 'registro_profissional']);

        $this->assertDatabaseCount('obras', 0);
    }

    #[Test]
    public function nao_cadastra_duas_obras_com_o_mesmo_codigo(): void
    {
        $this->comObraGravada();

        $this->post('/obras', [
            'codigo' => 'OBR-2026-007',
            'nome' => 'Outra',
            'cliente' => 'Outro',
            'responsavel_tecnico' => 'Outro',
            'registro_profissional' => 'CREA-SP 1/D',
        ])->assertSessionHas('erro', fn (string $erro): bool => str_contains($erro, 'Já existe uma obra'));

        $this->assertSame('Edifício Vista Serra', app(RepositorioDeObras::class)->porCodigo('OBR-2026-007')?->nome);
    }

    #[Test]
    public function o_formulario_devolve_o_que_foi_digitado_quando_recusa(): void
    {
        $this->post('/obras', ['codigo' => 'OBR-XYZ'])
            ->assertSessionHasInput('codigo', 'OBR-XYZ');
    }

    // ---------- detalhe ----------

    #[Test]
    public function a_pagina_da_obra_mostra_os_elementos_com_a_especificacao(): void
    {
        $this->comObraGravada();

        $this->get('/obras/OBR-2026-007')
            ->assertOk()
            ->assertSee('L3-P4')
            ->assertSee('C30')
            ->assertSee('100 ± 20 mm')
            ->assertSee('42,0 m³');
    }

    #[Test]
    public function obra_inexistente_da_404(): void
    {
        $this->get('/obras/NAO-EXISTE')->assertNotFound();
    }

    // ---------- cadastro de elemento ----------

    #[Test]
    public function cadastra_o_elemento_pelo_formulario(): void
    {
        $this->comObraGravada();

        $this->get('/obras/OBR-2026-007/elementos/novo')->assertOk()->assertSee('Cadastrar elemento');

        $this->post('/obras/OBR-2026-007/elementos', [
            'codigo' => 'p-ter',
            'tipo' => 'pilar',
            'descricao' => 'Pilares do térreo',
            'pavimento' => 'Térreo',
            'fck' => 35,
            'abatimento_mm' => 120,
            // Vírgula: o prepareForValidation do Form Request converte.
            'volume_previsto_m3' => '30,5',
        ])
            ->assertRedirect('/obras/OBR-2026-007')
            ->assertSessionHas('mensagem');

        $elemento = app(RepositorioDeElementos::class)->porCodigo('OBR-2026-007', 'P-TER');

        $this->assertSame('Pilares do térreo', $elemento?->descricao);
        $this->assertEqualsWithDelta(30.5, $elemento?->volumePrevistoEmM3 ?? 0.0, 0.001);
    }

    #[Test]
    public function o_dominio_recusa_peca_estrutural_com_c15(): void
    {
        $this->comObraGravada();

        $this->post('/obras/OBR-2026-007/elementos', [
            'codigo' => 'P-1',
            'tipo' => 'pilar',
            'descricao' => 'Pilar',
            'fck' => 15,
            'abatimento_mm' => 100,
            'volume_previsto_m3' => '5,0',
        ])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'no mínimo C20'));
    }

    #[Test]
    public function o_form_request_recusa_tipo_e_fck_fora_do_enum(): void
    {
        $this->comObraGravada();

        $this->post('/obras/OBR-2026-007/elementos', [
            'codigo' => 'X-1',
            'tipo' => 'ponte',
            'descricao' => 'Qualquer',
            'fck' => 27,
            'abatimento_mm' => 100,
            'volume_previsto_m3' => '5,0',
        ])->assertSessionHasErrors(['tipo', 'fck']);
    }

    #[Test]
    public function o_form_request_recusa_abatimento_fora_da_faixa(): void
    {
        $this->comObraGravada();

        $this->post('/obras/OBR-2026-007/elementos', [
            'codigo' => 'X-1',
            'tipo' => 'laje',
            'descricao' => 'Qualquer',
            'fck' => 30,
            'abatimento_mm' => 500,
            'volume_previsto_m3' => '5,0',
        ])->assertSessionHasErrors('abatimento_mm');
    }

    // ---------- o que o framework dá de graça ----------

    #[Test]
    public function verbo_errado_da_405(): void
    {
        $this->comObraGravada();

        // O roteador distingue "não existe" de "existe mas não aceita esse
        // verbo" — na versão em PHP puro isso foi escrito à mão.
        $this->delete('/obras')->assertStatus(405);
    }

    /*
     * Não dá para testar "POST sem token é recusado": o middleware de CSRF
     * do Laravel se desliga quando detecta que está rodando em teste
     * (PreventRequestForgery::runningUnitTests). O que se pode provar aqui
     * é que o formulário carrega o campo — o resto é responsabilidade do
     * framework, que tem os próprios testes.
     */
    #[Test]
    public function o_formulario_traz_o_campo_de_token(): void
    {
        $this->get('/obras/nova')->assertSee('name="_token"', false);
        $this->get('/obras/OBR-2026-007/elementos/novo');
    }
}
