<?php

declare(strict_types=1);

namespace Tests\Feature\Persistencia;

use App\Aplicacao\FormarLote;
use App\Aplicacao\JulgarLote;
use App\Aplicacao\TratarNaoConformidade;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\GrupoDeSolicitacao;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\Lote\SituacaoDoLote;
use App\Dominio\Lote\TipoDeAmostragem;
use App\Dominio\NaoConformidade\Desfecho;
use App\Dominio\NaoConformidade\Providencia;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\NaoConformidade\ResultadoDaProvidencia;
use App\Dominio\NaoConformidade\SituacaoDaNaoConformidade;
use App\Dominio\NaoConformidade\TipoDeProvidencia;
use App\Dominio\Obra\RepositorioDeObras;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;
use Tests\TestCase;

/*
 * O caminho completo da aceitação: formar o lote, julgar pela NBR 12655 e,
 * quando reprova, abrir e tratar a não conformidade.
 *
 * É aqui que a transação do Laravel aparece: FormarLote e JulgarLote
 * trocaram o beginTransaction/commit escrito à mão por DB::transaction.
 */
final class LotesENaoConformidadesTest extends TestCase
{
    use ObjetosDeExemplo;
    use RefreshDatabase;
    use RegrasDeDominio;

    /** @var int[] */
    private array $numeros = [];

    /** Duas concretagens C30 concluídas, seis exemplares no total: fck,est = 29,2. */
    private function comLoteJulgavel(): void
    {
        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());

        $concretagens = app(RepositorioDeConcretagens::class);

        $this->numeros = [
            $concretagens->salvar($this->concretagemComResultados(0, [32.4, 29.8, 31.1])),
            $concretagens->salvar($this->concretagemComResultados(0, [33.6, 30.5, 35.0], data: '2026-03-11')),
        ];
    }

    private function formar(TipoDeAmostragem $amostragem = TipoDeAmostragem::Parcial): void
    {
        app(FormarLote::class)->executar(
            'OBR-2026-007',
            ClasseDeResistencia::C30,
            GrupoDeSolicitacao::Horizontal,
            CondicaoDePreparo::A,
            $amostragem,
            $this->numeros,
        );
    }

    // ---------- formar o lote ----------

    #[Test]
    public function forma_o_lote_e_le_de_volta_com_as_concretagens(): void
    {
        $this->comLoteJulgavel();
        $this->formar();

        $lote = app(RepositorioDeLotes::class)->porNumero('OBR-2026-007', 1);

        $this->assertCount(2, $lote?->concretagens() ?? []);
        $this->assertAproximado(48.0, $lote?->volumeEmM3() ?? 0.0, 'seis cargas de 8 m³');
        $this->assertCount(6, $lote?->exemplaresDeAceitacao() ?? []);
        $this->assertSame(SituacaoDoLote::Aberto, $lote?->situacao());
    }

    #[Test]
    public function uma_concretagem_entra_em_um_lote_so(): void
    {
        $this->comLoteJulgavel();
        $this->formar();

        $this->recusa(fn () => $this->formar(), 'já está no lote 1');
    }

    // ---------- julgar ----------

    #[Test]
    public function julga_grava_o_veredito_e_a_memoria_de_calculo_sobrevive(): void
    {
        $this->comLoteJulgavel();
        $this->formar();

        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        $lote = app(RepositorioDeLotes::class)->porNumero('OBR-2026-007', 1);
        $estimativa = $lote?->estimativa();

        $this->assertSame(SituacaoDoLote::NaoConforme, $lote?->situacao(), '29,2 não atende C30');
        $this->assertAproximado(29.2, $estimativa?->fckEstimadoEmMPa ?? 0.0);
        $this->assertAproximado(0.86, $estimativa?->psi6 ?? 0.0);
        $this->assertSame([29.8, 30.5, 31.1, 32.4, 33.6, 35.0], $estimativa?->valoresOrdenados);
        $this->assertStringContainsString('m = 3', $estimativa?->metodo ?? '', 'o método foi guardado');
        $this->assertSame('2026-04-10 10:00', $lote?->julgadoEm()?->format('Y-m-d H:i'));
    }

    #[Test]
    public function o_lote_reprovado_abre_a_nao_conformidade_na_mesma_transacao(): void
    {
        $this->comLoteJulgavel();
        $this->formar();

        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        $naoConformidade = app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1);

        $this->assertSame(SituacaoDaNaoConformidade::Aberta, $naoConformidade?->situacao());
        $this->assertAproximado(30.0, $naoConformidade?->fckDeProjetoEmMPa ?? 0.0);
        $this->assertAproximado(29.2, $naoConformidade?->fckEstimadoEmMPa ?? 0.0);
        $this->assertAproximado(0.8, $naoConformidade?->deficitEmMPa() ?? 0.0);
        $this->assertCount(1, app(RepositorioDeNaoConformidades::class)->abertas());
    }

    #[Test]
    public function o_lote_aceito_nao_abre_nada(): void
    {
        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());

        // Seis exemplares folgados: C30 com fck estimado bem acima de 30.
        $this->numeros = [
            app(RepositorioDeConcretagens::class)->salvar(
                $this->concretagemComResultados(0, [36.0, 37.5, 38.2, 36.8, 39.0, 40.1]),
            ),
        ];

        $this->formar();

        $lote = app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        $this->assertSame(SituacaoDoLote::Aceito, $lote->situacao());
        $this->assertNull(app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1));
    }

    #[Test]
    public function nao_julga_duas_vezes(): void
    {
        $this->comLoteJulgavel();
        $this->formar();

        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        $this->recusa(
            static fn () => app(JulgarLote::class)->executar('OBR-2026-007', 1),
            'já foi julgado',
        );
    }

    #[Test]
    public function amostragem_parcial_exige_seis_exemplares(): void
    {
        app(RepositorioDeObras::class)->salvar($this->obraDeTeste());
        app(RepositorioDeElementos::class)->salvar('OBR-2026-007', $this->lajeDeTeste());

        $this->numeros = [
            app(RepositorioDeConcretagens::class)->salvar($this->concretagemComResultados(0, [27.0, 26.4])),
        ];

        $this->formar();

        $this->recusa(
            static fn () => app(JulgarLote::class)->executar('OBR-2026-007', 1),
            'ao menos 6 exemplares',
        );
    }

    // ---------- tratar a não conformidade ----------

    #[Test]
    public function providencias_e_encerramento_sobrevivem_a_releitura(): void
    {
        $this->comLoteJulgavel();
        $this->formar();
        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        $tratar = app(TratarNaoConformidade::class);

        $tratar->registrarProvidencia('OBR-2026-007', 1, new Providencia(
            TipoDeProvidencia::EnsaioNaoDestrutivo,
            $this->momento('2026-04-12'),
            'Esclerometria em 12 pontos da laje; menor índice no eixo 3.',
            ResultadoDaProvidencia::Informativo,
            'Laboratório Litoral',
        ));

        $tratar->registrarProvidencia('OBR-2026-007', 1, new Providencia(
            TipoDeProvidencia::ExtracaoDeTestemunhos,
            $this->momento('2026-04-18'),
            'Três testemunhos extraídos no eixo 3 e rompidos conforme a NBR 7680.',
            ResultadoDaProvidencia::Favoravel,
            'Laboratório Litoral',
            31.4,
        ));

        $lida = app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1);

        $this->assertCount(2, $lida?->providencias() ?? []);
        $this->assertSame(TipoDeProvidencia::ExtracaoDeTestemunhos, $lida?->providencias()[1]->tipo);
        $this->assertAproximado(31.4, $lida?->providencias()[1]->fckObtidoEmMPa ?? 0.0);
        $this->assertSame([Desfecho::EstruturaAceita], $lida?->desfechosPossiveis());

        $tratar->encerrar(
            'OBR-2026-007',
            1,
            Desfecho::EstruturaAceita,
            'Testemunhos a 31,4 MPa: a peça atende.',
            $this->momento('2026-04-20 09:00'),
        );

        $encerrada = app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1);

        $this->assertSame(SituacaoDaNaoConformidade::Encerrada, $encerrada?->situacao());
        $this->assertSame(Desfecho::EstruturaAceita, $encerrada?->desfecho());
        $this->assertSame('Testemunhos a 31,4 MPa: a peça atende.', $encerrada?->parecer());
        $this->assertSame([], app(RepositorioDeNaoConformidades::class)->abertas());
    }

    #[Test]
    public function a_recusa_do_dominio_nao_deixa_nada_no_banco(): void
    {
        $this->comLoteJulgavel();
        $this->formar();
        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        $this->recusa(
            static fn () => app(TratarNaoConformidade::class)->encerrar(
                'OBR-2026-007',
                1,
                Desfecho::EstruturaAceita,
                'Sem base',
                new \DateTimeImmutable('2026-04-20'),
            ),
            'sem tratamento',
        );

        $this->assertSame(
            SituacaoDaNaoConformidade::Aberta,
            app(RepositorioDeNaoConformidades::class)->doLote('OBR-2026-007', 1)?->situacao(),
        );
    }

    #[Test]
    public function o_gatilho_recusa_nao_conformidade_de_lote_que_atendeu(): void
    {
        $this->comLoteJulgavel();
        $this->formar();
        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        // fck estimado acima do de projeto: não é não conformidade.
        $this->recusaCom(
            \Illuminate\Database\QueryException::class,
            static fn () => \Illuminate\Support\Facades\DB::table('nao_conformidades')
                ->where('lote_numero', 1)
                ->update(['fck_estimado_mpa' => 31.0]),
            'lote abaixo do fck',
        );
    }

    #[Test]
    public function apagar_a_obra_leva_lote_e_nao_conformidade_junto(): void
    {
        $this->comLoteJulgavel();
        $this->formar();
        app(JulgarLote::class)->executar('OBR-2026-007', 1, $this->momento('2026-04-10 10:00'));

        app(TratarNaoConformidade::class)->registrarProvidencia('OBR-2026-007', 1, new Providencia(
            TipoDeProvidencia::RevisaoDeProjeto,
            $this->momento('2026-04-12'),
            'Projetista reverificou a laje com fck de 29,2 MPa.',
            ResultadoDaProvidencia::Favoravel,
            'Projetista',
        ));

        \Illuminate\Support\Facades\DB::table('obras')->where('codigo', 'OBR-2026-007')->delete();

        $this->assertDatabaseCount('lotes', 0);
        $this->assertDatabaseCount('lote_concretagens', 0);
        $this->assertDatabaseCount('nao_conformidades', 0);
        $this->assertDatabaseCount('providencias', 0);
    }
}
