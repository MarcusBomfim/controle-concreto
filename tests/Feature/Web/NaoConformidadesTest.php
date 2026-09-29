<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\Obra\RepositorioDeObras;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\Autenticacao;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\TestCase;

/*
 * O tratamento do lote reprovado: registrar providências até que uma delas
 * sustente um desfecho, e então encerrar.
 *
 * O que o Form Request confere aqui é pouco de propósito. As regras que
 * importam — descrição com detalhe, fck só em testemunho, ensaio não
 * destrutivo é sempre informativo, desfecho só com prova — moram na
 * Providencia e na NaoConformidade, com a razão de norma escrita junto.
 */
final class NaoConformidadesTest extends TestCase
{
    use Autenticacao;
    use ObjetosDeExemplo;
    use RefreshDatabase;

    private string $hoje;

    protected function setUp(): void
    {
        parent::setUp();

        $this->comoEngenheiro();

        $this->hoje = (new DateTimeImmutable('today'))->format('Y-m-d');

        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());

        // Laje C30 que rompeu a 24 MPa: o lote reprova e a não conformidade abre.
        app(RepositorioDeConcretagens::class)->salvar($this->concretagemComResultados(0, [24.0]));

        $this->post('/obras/OBR-2026-007/lotes', [
            'concretagens' => ['1'],
            'condicao' => 'a',
            'amostragem' => 'total',
        ]);

        $this->post('/obras/OBR-2026-007/lotes/1/julgar');
    }

    /** @param array<string, mixed> $campos */
    private function providencia(array $campos = []): \Illuminate\Testing\TestResponse
    {
        return $this->post('/obras/OBR-2026-007/lotes/1/nao-conformidade/providencias', $campos + [
            'tipo' => 'revisao_de_projeto',
            'realizada_em' => $this->hoje,
            'resultado' => 'desfavoravel',
            'descricao' => 'Recalculada a laje L3 com o fck obtido. A flecha no vão maior passa do limite.',
            'responsavel' => 'Marcus Bomfim',
        ]);
    }

    private function recarregar(): ?object
    {
        return app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1);
    }

    // ---------- a tela ----------

    #[Test]
    public function a_tela_mostra_o_deficit_e_o_que_falta(): void
    {
        $this->get('/obras/OBR-2026-007/lotes/1/nao-conformidade')
            ->assertOk()
            ->assertSee('Não conformidade')
            ->assertSee('24,0 MPa')
            ->assertSee('30,0 MPa')
            // 6 MPa de 30 são 20 %.
            ->assertSee('20,0 %')
            ->assertSee('Nenhuma providência registrada ainda')
            ->assertSee('Nenhum desfecho está sustentado ainda');
    }

    #[Test]
    public function lote_aceito_nao_tem_nao_conformidade(): void
    {
        app(RepositorioDeConcretagens::class)->salvar(
            $this->concretagemComResultados(0, [33.0], null, '2026-03-11'),
        );

        $this->post('/obras/OBR-2026-007/lotes', [
            'concretagens' => ['2'],
            'condicao' => 'a',
            'amostragem' => 'total',
        ]);
        $this->post('/obras/OBR-2026-007/lotes/2/julgar');

        $this->get('/obras/OBR-2026-007/lotes/2/nao-conformidade')->assertNotFound();
    }

    // ---------- providências ----------

    #[Test]
    public function registra_a_providencia_e_a_lista(): void
    {
        $this->providencia()
            ->assertRedirect('/obras/OBR-2026-007/lotes/1/nao-conformidade')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'Revisão'));

        $this->assertCount(1, $this->recarregar()?->providencias() ?? []);

        $this->get('/obras/OBR-2026-007/lotes/1/nao-conformidade')
            ->assertSee('Desfavorável')
            ->assertSee('Marcus Bomfim');
    }

    #[Test]
    public function a_entidade_recusa_descricao_curta(): void
    {
        // O Form Request não checa o mínimo de propósito: a razão está na
        // Providencia, e a mensagem dela diz o que escrever.
        $this->providencia(['descricao' => 'ok'])
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'ao menos 20 caracteres'));

        $this->assertCount(0, $this->recarregar()?->providencias() ?? []);
    }

    #[Test]
    public function a_entidade_recusa_fck_fora_do_testemunho(): void
    {
        $this->providencia(['fck_obtido_mpa' => '27,5'])
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'só se informa na extração de testemunhos'));
    }

    #[Test]
    public function a_entidade_exige_o_fck_no_testemunho(): void
    {
        $this->providencia([
            'tipo' => 'extracao_de_testemunhos',
            'resultado' => 'favoravel',
            'descricao' => 'Extraídos quatro testemunhos da região central da laje L3.',
        ])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'Informe o fck obtido'));
    }

    #[Test]
    public function a_entidade_exige_que_ensaio_nao_destrutivo_seja_informativo(): void
    {
        $this->providencia([
            'tipo' => 'ensaio_nao_destrutivo',
            'resultado' => 'favoravel',
            'descricao' => 'Esclerometria em doze pontos da laje L3, malha de um metro.',
        ])->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'localiza'));
    }

    #[Test]
    public function o_form_request_recusa_providencia_no_futuro(): void
    {
        $this->providencia(['realizada_em' => (new DateTimeImmutable('+3 days'))->format('Y-m-d')])
            ->assertSessionHasErrors('realizada_em');
    }

    #[Test]
    public function o_form_request_recusa_tipo_e_resultado_fora_do_enum(): void
    {
        $this->providencia(['tipo' => 'rezar', 'resultado' => 'mais_ou_menos'])
            ->assertSessionHasErrors(['tipo', 'resultado']);
    }

    // ---------- encerrar ----------

    #[Test]
    public function o_desfecho_so_aparece_quando_uma_providencia_o_sustenta(): void
    {
        $this->providencia([
            'tipo' => 'extracao_de_testemunhos',
            'resultado' => 'favoravel',
            'fck_obtido_mpa' => '31,2',
            'descricao' => 'Quatro testemunhos da região central da laje L3, todos acima do projeto.',
        ]);

        $this->get('/obras/OBR-2026-007/lotes/1/nao-conformidade')
            ->assertOk()
            ->assertSee('Encerrar não conformidade')
            ->assertDontSee('Nenhum desfecho está sustentado ainda');
    }

    #[Test]
    public function encerra_com_o_desfecho_sustentado(): void
    {
        $this->providencia([
            'tipo' => 'extracao_de_testemunhos',
            'resultado' => 'favoravel',
            'fck_obtido_mpa' => '31,2',
            'descricao' => 'Quatro testemunhos da região central da laje L3, todos acima do projeto.',
        ]);

        $this->post('/obras/OBR-2026-007/lotes/1/nao-conformidade/encerrar', [
            'desfecho' => 'estrutura_aceita',
            'parecer' => 'Os testemunhos mostraram resistência acima do fck de projeto na região crítica.',
        ])
            ->assertRedirect('/obras/OBR-2026-007/lotes/1/nao-conformidade')
            ->assertSessionHas('mensagem', fn (string $m): bool => str_contains($m, 'encerrada'));

        $this->assertFalse($this->recarregar()?->estaAberta());

        $this->get('/obras/OBR-2026-007/lotes/1/nao-conformidade')
            ->assertSee('Parecer de encerramento')
            ->assertDontSee('Registrar providência');
    }

    #[Test]
    public function a_entidade_recusa_desfecho_sem_prova(): void
    {
        /*
         * A tela só oferece os desfechos sustentados — mas um POST não vem da
         * tela, vem do navegador. Aceitar a estrutura sem revisão de projeto
         * ou testemunho favorável seria aceitar no grito.
         */
        $this->post('/obras/OBR-2026-007/lotes/1/nao-conformidade/encerrar', [
            'desfecho' => 'estrutura_aceita',
            'parecer' => 'Está tudo bem, pode liberar a peça para a próxima etapa da obra.',
        ])->assertSessionHas('erro');

        $this->assertTrue($this->recarregar()?->estaAberta());
    }

    #[Test]
    public function o_form_request_recusa_desfecho_fora_do_enum(): void
    {
        $this->post('/obras/OBR-2026-007/lotes/1/nao-conformidade/encerrar', [
            'desfecho' => 'deixa_pra_la',
            'parecer' => 'Qualquer coisa',
        ])->assertSessionHasErrors('desfecho');
    }

    #[Test]
    public function nao_registra_providencia_em_nao_conformidade_encerrada(): void
    {
        $this->providencia([
            'tipo' => 'extracao_de_testemunhos',
            'resultado' => 'favoravel',
            'fck_obtido_mpa' => '31,2',
            'descricao' => 'Quatro testemunhos da região central da laje L3, todos acima do projeto.',
        ]);

        $this->post('/obras/OBR-2026-007/lotes/1/nao-conformidade/encerrar', [
            'desfecho' => 'estrutura_aceita',
            'parecer' => 'Os testemunhos mostraram resistência acima do fck de projeto na região crítica.',
        ]);

        $this->providencia()->assertSessionHas('erro');

        $this->assertCount(1, $this->recarregar()?->providencias() ?? []);
    }
}
