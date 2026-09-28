<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\AgendaDoLaboratorio;
use App\Aplicacao\ItemDaAgenda;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;

/**
 * A tela principal: o que venceu, o que rompe agora, o que vem aí.
 *
 * O controlador recebe a interface `AgendaDoLaboratorio`, não um repositório
 * de concretagens: a agenda é modelo de leitura, montada por uma consulta com
 * JOIN, e hidratar o agregado inteiro só para listar cilindros custaria caro
 * sem dar nada em troca.
 */
final class AgendaController extends Controller
{
    /**
     * A maior tolerância de idade é a de 91 dias: 48 h. Um corpo de prova cuja
     * janela está aberta agora tem rompimento previsto a no máximo essa
     * distância — é o que delimita a consulta.
     */
    private const MAIOR_TOLERANCIA_EM_HORAS = 48;

    private const DIAS_DE_HORIZONTE = 7;

    public function __construct(private readonly AgendaDoLaboratorio $agenda)
    {
    }

    public function index(): View
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

        return view('agenda', [
            'agora' => $agora,
            'vencidos' => $this->agenda->vencidos($agora),
            'naJanela' => $naJanela,
            'proximos' => $proximos,
            'horizonteEmDias' => self::DIAS_DE_HORIZONTE,
            'totalEmCura' => $this->agenda->totalEmCura(),
        ]);
    }
}
