<?php

declare(strict_types=1);

namespace Tests\Feature\Persistencia;

use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Concretagem\SituacaoDaConcretagem;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Obra\RepositorioDeObras;
use App\Dominio\Usuario\Papel;
use App\Dominio\Usuario\RepositorioDeUsuarios;
use App\Dominio\Usuario\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;
use Tests\TestCase;

/*
 * Estes testes estendem o TestCase do Laravel, e não o do PHPUnit: precisam
 * do container para resolver as interfaces e de um banco para gravar.
 *
 * O RefreshDatabase roda as migrations num SQLite em memória e desfaz tudo
 * ao fim de cada teste. É o que o bancoDeTeste() fazia à mão na versão em
 * PHP puro, com a diferença de que aqui as migrations são as de verdade.
 */
final class PersistenciaTest extends TestCase
{
    use ObjetosDeExemplo;
    use RefreshDatabase;
    use RegrasDeDominio;

    private function repositorioDeObras(): RepositorioDeObras
    {
        return app(RepositorioDeObras::class);
    }

    private function repositorioDeElementos(): RepositorioDeElementos
    {
        return app(RepositorioDeElementos::class);
    }

    private function repositorioDeConcretagens(): RepositorioDeConcretagens
    {
        return app(RepositorioDeConcretagens::class);
    }

    /** Obra e laje gravadas, prontas para receber concretagem. */
    private function comObraGravada(): void
    {
        $this->repositorioDeObras()->salvar($this->obraDeTeste());
        $this->repositorioDeElementos()->salvar('OBR-2026-007', $this->lajeDeTeste());
        $this->repositorioDeElementos()->salvar('OBR-2026-007', $this->pilaresDeTeste());
    }

    // ---------- o container entrega a implementação certa ----------

    #[Test]
    public function o_container_resolve_as_interfaces_do_dominio(): void
    {
        $this->assertInstanceOf(
            \App\Persistencia\RepositorioDeObrasEmBanco::class,
            app(RepositorioDeObras::class),
        );

        $this->assertInstanceOf(
            \App\Persistencia\RepositorioDeLotesEmBanco::class,
            app(\App\Dominio\Lote\RepositorioDeLotes::class),
        );
    }

    // ---------- obras e elementos ----------

    #[Test]
    public function grava_a_obra_e_le_de_volta(): void
    {
        $this->repositorioDeObras()->salvar($this->obraDeTeste());

        $lida = $this->repositorioDeObras()->porCodigo('OBR-2026-007');

        $this->assertSame('Edifício Vista Serra', $lida?->nome);
        $this->assertSame('CREA-SP 123456/D', $lida?->registroProfissional);
        $this->assertTrue($this->repositorioDeObras()->existe('obr-2026-007'), 'acha em minúsculas');
        $this->assertNull($this->repositorioDeObras()->porCodigo('NAO-EXISTE'));
    }

    #[Test]
    public function salvar_de_novo_atualiza_em_vez_de_duplicar(): void
    {
        $this->repositorioDeObras()->salvar($this->obraDeTeste());
        $this->repositorioDeObras()->salvar(new \App\Dominio\Obra\Obra(
            'OBR-2026-007',
            'Nome novo',
            'Cliente novo',
            'Marcus Bomfim',
            'CREA-SP 123456/D',
        ));

        $this->assertCount(1, $this->repositorioDeObras()->todas());
        $this->assertSame('Nome novo', $this->repositorioDeObras()->porCodigo('OBR-2026-007')?->nome);
    }

    #[Test]
    public function grava_o_elemento_com_a_especificacao_de_projeto(): void
    {
        $this->comObraGravada();

        $laje = $this->repositorioDeElementos()->porCodigo('OBR-2026-007', 'l3-p4');

        $this->assertSame('L3-P4', $laje?->codigo);
        $this->assertSame(\App\Dominio\Concreto\ClasseDeResistencia::C30, $laje?->classe);
        $this->assertSame(100, $laje?->abatimento->especificadoEmMm);
        $this->assertSame('4º pavimento', $laje?->pavimento);
        $this->assertCount(2, $this->repositorioDeElementos()->daObra('OBR-2026-007'));
    }

    // ---------- a concretagem, que atravessa quatro tabelas ----------

    #[Test]
    public function grava_a_concretagem_inteira_e_recompoe_o_agregado(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, 100, '08:00', '08:50', notaFiscal: 'NF-1');
        $this->chegaCarga($concretagem, 160, '08:30', '09:20', notaFiscal: 'NF-2');
        $this->moldaPadrao($concretagem, 1);

        $numero = $this->repositorioDeConcretagens()->salvar($concretagem);

        $this->assertSame(1, $numero, 'primeira concretagem da obra');

        $lida = $this->repositorioDeConcretagens()->porNumero('OBR-2026-007', 1);

        $this->assertCount(2, $lida?->cargas() ?? []);
        $this->assertCount(1, $lida?->cargasDevolvidas() ?? [], 'a de 160 mm foi devolvida');
        $this->assertAproximado(8.0, $lida?->volumeAceitoEmM3() ?? 0.0);
        $this->assertCount(2, $lida?->exemplares() ?? [], '7 e 28 dias');
        $this->assertCount(4, $lida?->corposDeProva() ?? [], 'dois cilindros por exemplar');
        $this->assertSame('L3-P4', $lida?->elemento->codigo, 'o elemento vem junto');
    }

    #[Test]
    public function a_janela_de_rompimento_sobrevive_a_releitura(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem);
        $this->moldaPadrao($concretagem);

        $this->repositorioDeConcretagens()->salvar($concretagem);

        $lida = $this->repositorioDeConcretagens()->porNumero('OBR-2026-007', 1);
        $corpoDeProva = $lida?->corpoDeProva('C1-28d-A');

        // Moldado 10/03 às 9h, 28 dias, tolerância de 20 h.
        $this->assertSame('2026-04-07 09:00', $corpoDeProva?->rompimentoPrevisto()->format('Y-m-d H:i'));
        $this->assertSame('2026-04-06 13:00', $corpoDeProva?->inicioDaJanela()->format('Y-m-d H:i'));
        $this->assertSame('2026-04-08 05:00', $corpoDeProva?->fimDaJanela()->format('Y-m-d H:i'));
    }

    #[Test]
    public function numera_em_sequencia_dentro_da_obra(): void
    {
        $this->comObraGravada();

        $this->assertSame(1, $this->repositorioDeConcretagens()->salvar($this->concretagemDeTeste()));
        $this->assertSame(2, $this->repositorioDeConcretagens()->salvar($this->concretagemDeTeste()));
        $this->assertCount(2, $this->repositorioDeConcretagens()->daObra('OBR-2026-007'));
    }

    #[Test]
    public function salvar_de_novo_muda_a_situacao_e_acrescenta_cargas(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem, notaFiscal: 'NF-1');
        $this->moldaPadrao($concretagem);
        $numero = $this->repositorioDeConcretagens()->salvar($concretagem);

        /*
         * salvar() devolve o número, mas não o escreve no agregado — quem
         * faz isso é o caso de uso. Sem esta linha, a segunda gravação
         * pediria um número novo e criaria outra concretagem.
         */
        $concretagem->definirNumero($numero);

        $this->chegaCarga($concretagem, notaFiscal: 'NF-2', saida: '09:00', chegada: '09:45');
        $concretagem->concluir();
        $this->repositorioDeConcretagens()->salvar($concretagem);

        $lida = $this->repositorioDeConcretagens()->porNumero('OBR-2026-007', $numero);

        $this->assertSame(SituacaoDaConcretagem::Concluida, $lida?->situacao());
        $this->assertCount(2, $lida?->cargas() ?? []);
    }

    #[Test]
    public function lista_as_concretagens_do_elemento(): void
    {
        $this->comObraGravada();

        $this->repositorioDeConcretagens()->salvar($this->concretagemDeTeste());

        $this->assertCount(1, $this->repositorioDeConcretagens()->doElemento('OBR-2026-007', 'L3-P4'));
        $this->assertCount(0, $this->repositorioDeConcretagens()->doElemento('OBR-2026-007', 'P-T'));
    }

    // ---------- o que o banco garante sozinho ----------

    #[Test]
    public function apagar_a_obra_leva_tudo_em_cascata(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem);
        $this->moldaPadrao($concretagem);
        $this->repositorioDeConcretagens()->salvar($concretagem);

        $this->assertDatabaseCount('corpos_de_prova', 4);

        \Illuminate\Support\Facades\DB::table('obras')->where('codigo', 'OBR-2026-007')->delete();

        $this->assertDatabaseCount('elementos', 0);
        $this->assertDatabaseCount('concretagens', 0);
        $this->assertDatabaseCount('cargas', 0);
        $this->assertDatabaseCount('exemplares', 0);
        $this->assertDatabaseCount('corpos_de_prova', 0);
    }

    #[Test]
    public function o_gatilho_impede_rompido_sem_resultado(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem);
        $this->moldaPadrao($concretagem);
        $this->repositorioDeConcretagens()->salvar($concretagem);

        // Marcar como rompido sem preencher carga, diâmetro e resistência.
        $this->recusaCom(
            \Illuminate\Database\QueryException::class,
            static fn () => \Illuminate\Support\Facades\DB::table('corpos_de_prova')
                ->where('identificacao', 'C1-28d-A')
                ->update(['situacao' => 'rompido']),
            'rompido exige data',
        );
    }

    #[Test]
    public function o_gatilho_impede_a_situacao_de_regredir(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem);
        $this->moldaPadrao($concretagem);
        $this->repositorioDeConcretagens()->salvar($concretagem);

        \Illuminate\Support\Facades\DB::table('corpos_de_prova')
            ->where('identificacao', 'C1-28d-A')
            ->update(['situacao' => 'descartado', 'motivo_descarte' => 'Quebrou na desforma']);

        $this->recusaCom(
            \Illuminate\Database\QueryException::class,
            static fn () => \Illuminate\Support\Facades\DB::table('corpos_de_prova')
                ->where('identificacao', 'C1-28d-A')
                ->update(['situacao' => 'curando']),
            'nao muda de situacao',
        );
    }

    // ---------- contas de acesso ----------

    #[Test]
    public function grava_a_conta_e_encontra_pelo_email_em_qualquer_caixa(): void
    {
        $repositorio = app(RepositorioDeUsuarios::class);

        $repositorio->salvar(Usuario::criar('Marcus@Concreto.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123'));

        $lido = $repositorio->porEmail('MARCUS@CONCRETO.DEV');

        $this->assertSame('marcus@concreto.dev', $lido?->email);
        $this->assertSame(Papel::Engenheiro, $lido?->papel);
        $this->assertTrue($lido?->senhaConfere('Segredo@123') ?? false, 'o hash sobreviveu ao banco');
        $this->assertTrue($repositorio->existe('marcus@concreto.dev'));
    }

    #[Test]
    public function a_agenda_lista_o_que_esta_em_cura(): void
    {
        $this->comObraGravada();

        $concretagem = $this->concretagemDeTeste();
        $this->chegaCarga($concretagem);
        $concretagem->moldar(1, $this->hora('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);
        $this->repositorioDeConcretagens()->salvar($concretagem);

        $agenda = app(\App\Aplicacao\AgendaDoLaboratorio::class);

        $this->assertSame(2, $agenda->totalEmCura());

        $naJanela = $agenda->comRompimentoEntre($this->momento('2026-04-07 00:00'), $this->momento('2026-04-07 23:59'));

        $this->assertCount(2, $naJanela);
        $this->assertSame('OBR-2026-007', $naJanela[0]->obraCodigo);
        $this->assertSame('L3-P4 — Laje L3 (4º pavimento)', $naJanela[0]->elementoIdentificacao);
        $this->assertSame(30, $naJanela[0]->fckDeProjeto);
    }
}
