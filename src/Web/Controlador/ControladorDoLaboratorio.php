<?php

declare(strict_types=1);

namespace ControleConcreto\Web\Controlador;

use DateTimeImmutable;
use ControleConcreto\Aplicacao\AgendaDoLaboratorio;
use ControleConcreto\Aplicacao\DescartarCorpoDeProva;
use ControleConcreto\Aplicacao\ItemDaAgenda;
use ControleConcreto\Aplicacao\RegistrarRompimento;
use ControleConcreto\Dominio\Ensaio\DiametroDoCorpoDeProva;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;
use Throwable;

/**
 * A tela principal: o que venceu, o que rompe agora, o que vem aí.
 *
 * O resultado se lança direto da agenda, porque é na agenda que a pessoa da
 * prensa está quando o cilindro rompe. Ir até a obra, achar a concretagem e
 * procurar o corpo de prova seria o caminho que a planilha já obrigava.
 */
final class ControladorDoLaboratorio
{
    /**
     * A maior tolerância de idade é a de 91 dias: 48 h. Um corpo de prova cuja
     * janela está aberta agora tem rompimento previsto a no máximo essa
     * distância — é o que delimita a consulta.
     */
    private const MAIOR_TOLERANCIA_EM_HORAS = 48;

    private const DIAS_DE_HORIZONTE = 7;

    public function __construct(
        private readonly AgendaDoLaboratorio $agenda,
        private readonly RegistrarRompimento $registrar,
        private readonly DescartarCorpoDeProva $descartar,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function agenda(Requisicao $requisicao): Resposta
    {
        $agora = new DateTimeImmutable('now');

        $margem = self::MAIOR_TOLERANCIA_EM_HORAS;

        // Janela aberta neste instante: o que a prensa deve romper agora.
        $naJanela = array_values(array_filter(
            $this->agenda->comRompimentoEntre($agora->modify("-{$margem} hours"), $agora->modify("+{$margem} hours")),
            static fn (ItemDaAgenda $item): bool => $item->dentroDaJanela($agora),
        ));

        // Janela ainda fechada, mas que abre nos próximos dias: para planejar a semana.
        $proximos = array_values(array_filter(
            $this->agenda->comRompimentoEntre($agora, $agora->modify('+' . self::DIAS_DE_HORIZONTE . ' days')),
            static fn (ItemDaAgenda $item): bool => $item->aindaNaoAbriu($agora),
        ));

        return Resposta::html($this->visao->renderizar('agenda', [
            'agora' => $agora,
            'vencidos' => $this->agenda->vencidos($agora),
            'naJanela' => $naJanela,
            'proximos' => $proximos,
            'horizonteEmDias' => self::DIAS_DE_HORIZONTE,
            'totalEmCura' => $this->agenda->totalEmCura(),
            'diametros' => DiametroDoCorpoDeProva::cases(),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Agenda do laboratório'));
    }

    public function romper(Requisicao $requisicao): Resposta
    {
        $destino = $this->destinoDeRetorno($requisicao);

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $rompidoEm = $requisicao->campo('rompido_em');

            if ($rompidoEm === '') {
                throw new ExcecaoDeDominio('Informe a data e a hora do rompimento.');
            }

            $corpoDeProva = $this->registrar->executar(
                $requisicao->parametro('codigo'),
                (int) $requisicao->parametro('numero'),
                $requisicao->parametro('identificacao'),
                $requisicao->campoDecimal('carga_kn'),
                $requisicao->campoInteiro('diametro_mm', DiametroDoCorpoDeProva::DezCentimetros->value),
                new DateTimeImmutable($rompidoEm),
            );

            $this->sessao->guardarMensagem(sprintf(
                '%s rompido: %s.',
                $corpoDeProva->identificacao,
                $corpoDeProva->resultado()?->descricao() ?? '',
            ));
        } catch (ExcecaoDeDominio $erro) {
            // Regra de negócio: a mensagem já está escrita para quem está na prensa.
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível registrar o resultado. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar($destino);
    }

    public function descartar(Requisicao $requisicao): Resposta
    {
        $destino = $this->destinoDeRetorno($requisicao);

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $corpoDeProva = $this->descartar->executar(
                $requisicao->parametro('codigo'),
                (int) $requisicao->parametro('numero'),
                $requisicao->parametro('identificacao'),
                $requisicao->campo('motivo'),
            );

            $this->sessao->guardarMensagem("{$corpoDeProva->identificacao} descartado. O motivo ficou registrado.");
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        }

        return Resposta::redirecionar($destino);
    }

    /**
     * O mesmo formulário aparece na agenda e na tela da concretagem; o campo
     * "voltar" diz de onde veio. Só se aceita caminho local: um destino
     * absoluto vindo do formulário seria um redirecionamento aberto.
     */
    private function destinoDeRetorno(Requisicao $requisicao): string
    {
        $voltar = $requisicao->campo('voltar', '/');

        return str_starts_with($voltar, '/') && !str_starts_with($voltar, '//') ? $voltar : '/';
    }
}
