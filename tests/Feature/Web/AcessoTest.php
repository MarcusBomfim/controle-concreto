<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Obra\RepositorioDeObras;
use App\Dominio\Usuario\Papel;
use App\Models\Conta;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\Autenticacao;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\TestCase;

/*
 * Entrar, sair e quem pode o quê.
 *
 * A matriz de permissão é o que mais importa aqui: a tela esconder o botão
 * não é garantia nenhuma, porque o POST vem do navegador. O que garante é o
 * `can:` na rota — e é isso que estes testes exercitam, chamando as rotas
 * direto com cada papel.
 */
final class AcessoTest extends TestCase
{
    use Autenticacao;
    use ObjetosDeExemplo;
    use RefreshDatabase;

    private function comObraEConcretagem(): void
    {
        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());
        app(RepositorioDeConcretagens::class)->salvar($this->concretagemComResultados(0, [32.0]));
    }

    // ---------- entrar ----------

    #[Test]
    public function a_raiz_manda_quem_nao_entrou_para_o_login(): void
    {
        $this->get('/')->assertRedirect('/entrar');
    }

    #[Test]
    public function o_formulario_de_login_abre(): void
    {
        $this->get('/entrar')
            ->assertOk()
            ->assertSee('Entrar')
            ->assertSee('Contas de demonstração');
    }

    #[Test]
    public function entra_com_e_mail_e_senha_corretos(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'Segredo@123'])
            ->assertRedirect('/')
            ->assertSessionHas('mensagem');

        $this->assertTrue(Auth::check());
        $this->assertSame('engenheiro@teste.dev', Auth::user()?->email);
    }

    #[Test]
    public function o_e_mail_nao_diferencia_maiusculas(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        // A entidade grava em minúsculas; o formulário aceita como vier.
        $this->post('/entrar', ['email' => 'Engenheiro@Teste.DEV', 'senha' => 'Segredo@123'])
            ->assertRedirect('/');

        $this->assertTrue(Auth::check());
    }

    #[Test]
    public function a_mensagem_de_erro_nao_diz_qual_dos_dois_estava_errado(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        $senhaErrada = $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'errada']);
        $emailInexistente = $this->post('/entrar', ['email' => 'ninguem@teste.dev', 'senha' => 'Segredo@123']);

        $mensagem = 'E-mail ou senha incorretos.';

        $senhaErrada->assertSessionHas('erro', $mensagem);
        $emailInexistente->assertSessionHas('erro', $mensagem);

        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function conta_desativada_nao_entra(): void
    {
        $conta = $this->conta(Papel::Engenheiro, 'Segredo@123');
        $conta->forceFill(['ativo' => false])->save();

        // `ativo => true` vai junto das credenciais e vira cláusula where:
        // a conta desativada simplesmente não é encontrada.
        $this->post('/entrar', ['email' => $conta->email, 'senha' => 'Segredo@123'])
            ->assertSessionHas('erro');

        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function desativar_a_conta_derruba_a_sessao_aberta(): void
    {
        $conta = $this->comoEngenheiro();

        $this->get('/')->assertOk();

        $conta->forceFill(['ativo' => false])->save();

        $this->get('/')
            ->assertRedirect('/entrar')
            ->assertSessionHas('erro', fn (string $e): bool => str_contains($e, 'desativada'));
    }

    #[Test]
    public function o_form_request_recusa_e_mail_malformado(): void
    {
        $this->post('/entrar', ['email' => 'nao-e-email', 'senha' => 'x'])
            ->assertSessionHasErrors('email');
    }

    #[Test]
    public function quem_ja_entrou_nao_ve_o_formulario(): void
    {
        $this->comoEngenheiro();

        $this->get('/entrar')->assertRedirect('/');
    }

    // ---------- sair ----------

    #[Test]
    public function sai_e_volta_para_o_login(): void
    {
        $this->comoEngenheiro();

        $this->post('/sair')->assertRedirect('/entrar');

        $this->assertFalse(Auth::check());
    }

    // ---------- o destino pretendido ----------

    #[Test]
    public function depois_do_login_volta_para_onde_a_pessoa_queria_ir(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        /*
         * O destino não passa pelo navegador: o middleware guarda a URL na
         * sessão e o redirect()->intended() a lê de lá. Na versão em PHP
         * puro isso era "?voltar=" na query string, e precisava ser validado
         * contra redirecionamento aberto a cada uso.
         */
        $this->get('/obras')->assertRedirect('/entrar');

        $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'Segredo@123'])
            ->assertRedirect('/obras');
    }

    // ---------- a matriz de permissão ----------

    /** @return array<string, array{0: string, 1: string}> */
    public static function rotasDeLeitura(): array
    {
        return [
            'agenda' => ['get', '/'],
            'lista de obras' => ['get', '/obras'],
            'página da obra' => ['get', '/obras/OBR-2026-007'],
            'tela da concretagem' => ['get', '/obras/OBR-2026-007/concretagens/1'],
        ];
    }

    #[Test]
    #[DataProvider('rotasDeLeitura')]
    public function o_gestor_le_tudo(string $verbo, string $caminho): void
    {
        $this->comObraEConcretagem();
        $this->comoGestor();

        $this->{$verbo}($caminho)->assertOk();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function rotasDeOperacao(): array
    {
        return [
            'nova concretagem' => ['get', '/obras/OBR-2026-007/concretagens/nova'],
            'receber carga' => ['post', '/obras/OBR-2026-007/concretagens/1/cargas'],
            'moldar' => ['post', '/obras/OBR-2026-007/concretagens/1/moldagens'],
            'romper' => ['post', '/obras/OBR-2026-007/concretagens/1/corpos-de-prova/C1-28d-A/romper'],
        ];
    }

    #[Test]
    #[DataProvider('rotasDeOperacao')]
    public function o_gestor_nao_opera(string $verbo, string $caminho): void
    {
        $this->comObraEConcretagem();
        $this->comoGestor();

        $this->{$verbo}($caminho)->assertForbidden();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function rotasDeDecisao(): array
    {
        return [
            'nova obra' => ['get', '/obras/nova'],
            'gravar obra' => ['post', '/obras'],
            'novo elemento' => ['get', '/obras/OBR-2026-007/elementos/novo'],
            'formar lote' => ['get', '/obras/OBR-2026-007/lotes/novo'],
            'gravar lote' => ['post', '/obras/OBR-2026-007/lotes'],
        ];
    }

    #[Test]
    #[DataProvider('rotasDeDecisao')]
    public function o_laboratorista_nao_decide(string $verbo, string $caminho): void
    {
        $this->comObraEConcretagem();
        $this->comoLaboratorista();

        $this->{$verbo}($caminho)->assertForbidden();
    }

    #[Test]
    public function o_laboratorista_opera(): void
    {
        $this->comObraEConcretagem();
        $this->comoLaboratorista();

        $this->get('/obras/OBR-2026-007/concretagens/nova')->assertOk();
    }

    #[Test]
    public function o_engenheiro_faz_tudo(): void
    {
        $this->comObraEConcretagem();
        $this->comoEngenheiro();

        $this->get('/obras/nova')->assertOk();
        $this->get('/obras/OBR-2026-007/lotes/novo')->assertOk();
        $this->get('/obras/OBR-2026-007/concretagens/nova')->assertOk();
    }

    // ---------- a tela acompanha a permissão ----------

    #[Test]
    public function a_tela_nao_oferece_o_que_a_rota_vai_recusar(): void
    {
        $this->comObraEConcretagem();
        $this->comoGestor();

        $this->get('/obras')
            ->assertOk()
            ->assertDontSee('Nova obra');

        $this->get('/obras/OBR-2026-007')
            ->assertOk()
            ->assertDontSee('Nova concretagem')
            ->assertDontSee('Formar lote');

        $this->get('/obras/OBR-2026-007/concretagens/1')
            ->assertOk()
            ->assertDontSee('Receber caminhão');
    }

    #[Test]
    public function o_topo_mostra_quem_esta_logado_e_o_papel(): void
    {
        $this->comoLaboratorista();

        $this->get('/')
            ->assertOk()
            ->assertSee('Laboratorista de teste')
            ->assertSee('Sair');
    }

    #[Test]
    public function a_conta_e_a_identidade_que_o_guard_carrega(): void
    {
        $conta = $this->comoEngenheiro();

        // A tabela é nossa: a chave é o e-mail e a senha mora em hash_senha.
        $this->assertSame('engenheiro@teste.dev', $conta->getAuthIdentifier());
        $this->assertSame('hash_senha', $conta->getAuthPasswordName());
        $this->assertSame(Papel::Engenheiro, $conta->papel);
        $this->assertStringNotContainsString('hash_senha', json_encode($conta->toArray()) ?: '');
        $this->assertInstanceOf(Conta::class, Auth::user());
    }

    // ---------- limite de tentativas ----------

    /** Uma tentativa de login com a senha errada. */
    private function tentativaErrada(string $email = 'engenheiro@teste.dev'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/entrar', ['email' => $email, 'senha' => 'chute-' . Str::random(6)]);
    }

    #[Test]
    public function bloqueia_a_conta_depois_de_cinco_tentativas_erradas(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        for ($i = 1; $i <= 5; $i++) {
            $this->tentativaErrada()->assertSessionHas('erro', 'E-mail ou senha incorretos.');
        }

        // A sexta não diz mais "senha incorreta": diz para esperar.
        $this->tentativaErrada()->assertSessionHasErrors('email');
    }

    #[Test]
    public function bloqueado_nao_entra_nem_com_a_senha_certa(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        for ($i = 1; $i <= 5; $i++) {
            $this->tentativaErrada();
        }

        /*
         * A ordem importa: o limite é conferido ANTES de comparar a senha.
         * Se fosse depois, a senha certa escaparia do bloqueio — e o
         * atacante que acertasse na tentativa 50 entraria assim mesmo.
         */
        $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'Segredo@123'])
            ->assertSessionHasErrors('email');

        $this->assertFalse(Auth::check());
    }

    #[Test]
    public function entrar_zera_o_contador_da_conta(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        for ($i = 1; $i <= 4; $i++) {
            $this->tentativaErrada();
        }

        $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'Segredo@123'])
            ->assertRedirect('/');

        $this->post('/sair');

        // Com o contador zerado, há folga de novo — não bloqueia na primeira.
        $this->tentativaErrada()->assertSessionHas('erro', 'E-mail ou senha incorretos.');
    }

    #[Test]
    public function o_limite_por_ip_pega_quem_varre_muitos_e_mails(): void
    {
        /*
         * O contador por e-mail + IP sozinho tem um buraco: trocando o
         * e-mail a cada tentativa, nenhum deles chega a 5. O contador do IP
         * é o que fecha isso.
         */
        for ($i = 1; $i <= 20; $i++) {
            $this->tentativaErrada("alvo{$i}@teste.dev")->assertSessionHas('erro');
        }

        $this->tentativaErrada('alvo21@teste.dev')->assertSessionHasErrors('email');
    }

    #[Test]
    public function a_mensagem_de_bloqueio_diz_quanto_falta(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        for ($i = 1; $i <= 5; $i++) {
            $this->tentativaErrada();
        }

        $this->tentativaErrada()->assertSessionHasErrors('email');

        $this->assertStringContainsString(
            'Muitas tentativas',
            session('errors')?->get('email')[0] ?? '',
        );
    }

    // ---------- registro de segurança ----------

    #[Test]
    public function a_senha_nunca_entra_no_log_de_seguranca(): void
    {
        $this->conta(Papel::Engenheiro, 'Segredo@123');

        $registrado = [];

        Log::shouldReceive('channel')->with('seguranca')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function (string $evento, array $contexto) use (&$registrado): void {
            $registrado[] = $evento . ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE);
        });

        $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'MinhaSenhaSecreta@123']);
        $this->post('/entrar', ['email' => 'engenheiro@teste.dev', 'senha' => 'Segredo@123']);

        $tudo = implode("\n", $registrado);

        $this->assertStringContainsString('login.recusado', $tudo);
        $this->assertStringContainsString('login.aceito', $tudo);
        $this->assertStringContainsString('engenheiro@teste.dev', $tudo, 'o e-mail entra: é o que identifica');

        // E a senha não, em nenhuma das duas tentativas.
        $this->assertStringNotContainsString('MinhaSenhaSecreta@123', $tudo);
        $this->assertStringNotContainsString('Segredo@123', $tudo);
    }

    #[Test]
    public function o_acesso_negado_vai_para_o_log(): void
    {
        $this->comObraEConcretagem();
        $this->comoGestor();

        $eventos = [];

        Log::shouldReceive('channel')->with('seguranca')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function (string $evento, array $contexto) use (&$eventos): void {
            $eventos[$evento] = $contexto;
        });

        $this->get('/obras/nova')->assertForbidden();

        $this->assertArrayHasKey('acesso.negado', $eventos);
        $this->assertSame('GET obras/nova', $eventos['acesso.negado']['alvo']);
        $this->assertSame('gestor@teste.dev', $eventos['acesso.negado']['email']);
    }

    #[Test]
    public function o_canal_de_seguranca_escreve_em_arquivo_proprio(): void
    {
        $canal = config('logging.channels.seguranca');

        $this->assertSame('daily', $canal['driver']);
        $this->assertStringEndsWith('seguranca.log', $canal['path']);
        $this->assertSame(90, $canal['days']);
    }
}
