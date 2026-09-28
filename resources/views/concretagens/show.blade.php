@extends('layouts.app')
@use('App\Dominio\Concretagem\Concretagem')
@use('App\Support\Formato')

@section('titulo', 'Concretagem nº ' . $concretagem->numero() . ' — ' . $obra->nome)

@php
    $elemento = $concretagem->elemento;
    $emAndamento = $concretagem->situacao()->aceitaCarga();
    $ehHoje = $concretagem->data->format('Y-m-d') === $agora->format('Y-m-d');
    $aqui = route('concretagens.show', [$obra->codigo, $concretagem->numero()], false);

    $emCura = count(array_filter(
        $concretagem->corposDeProva(),
        static fn ($cp): bool => $cp->situacao()->aguardaRompimento(),
    ));
@endphp

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> ›
        <a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->codigo }}</a> ›
        Concretagem nº {{ $concretagem->numero() }}
    </nav>

    <header class="cabecalho">
        <div>
            <h1 class="titulo">
                Concretagem nº {{ $concretagem->numero() }} · {{ Formato::data($concretagem->data) }}
            </h1>
            <p class="cabecalho__nota">
                <strong>{{ $elemento->identificacao() }}</strong> —
                {{ $elemento->tipo->rotulo() }}, {{ $elemento->classe->rotulo() }},
                abatimento {{ $elemento->abatimento->faixa() }}
            </p>
            <p class="cabecalho__nota">
                Fornecedor: {{ $concretagem->fornecedor }} · Responsável: {{ $concretagem->responsavel }}
            </p>
        </div>

        <div class="cabecalho__acoes">
            <span class="etiqueta etiqueta--{{ $concretagem->situacao()->value }}">
                {{ $concretagem->situacao()->rotulo() }}
            </span>

            {{-- A tela do lote entra na Parte 5; por ora o número é só informação. --}}
            @if ($loteNumero !== null)
                <span class="etiqueta">Lote {{ $loteNumero }}</span>
            @endif

            @if ($emAndamento && $concretagem->cargasAceitas() !== [])
                <form method="post" action="{{ route('concretagens.concluir', [$obra->codigo, $concretagem->numero()]) }}"
                      onsubmit="return confirm('Concluir a concretagem nº {{ $concretagem->numero() }}? Depois disso ela não recebe mais cargas nem moldagens.');">
                    @csrf
                    <button type="submit" class="botao botao--primario">Concluir concretagem</button>
                </form>
            @endif

            @if ($emAndamento && $concretagem->cargasAceitas() === [])
                <form method="post" action="{{ route('concretagens.cancelar', [$obra->codigo, $concretagem->numero()]) }}"
                      onsubmit="return confirm('Cancelar a concretagem nº {{ $concretagem->numero() }}?');">
                    @csrf
                    <button type="submit" class="botao botao--perigo">Cancelar concretagem</button>
                </form>
            @endif
        </div>
    </header>

    <section class="painel">
        <article class="indicador">
            <span class="indicador__rotulo">Cargas</span>
            <strong class="indicador__valor">{{ count($concretagem->cargas()) }}</strong>
            <span class="indicador__nota">{{ count($concretagem->cargasDevolvidas()) }} devolvida(s)</span>
        </article>
        <article class="indicador">
            <span class="indicador__rotulo">Volume aceito</span>
            <strong class="indicador__valor">{{ Formato::metrosCubicos($concretagem->volumeAceitoEmM3()) }}</strong>
            <span class="indicador__nota">
                de {{ Formato::metrosCubicos($elemento->volumePrevistoEmM3) }} previstos para a peça
            </span>
        </article>
        <article class="indicador {{ $concretagem->volumeDevolvidoEmM3() > 0 ? 'indicador--alerta' : '' }}">
            <span class="indicador__rotulo">Volume devolvido</span>
            <strong class="indicador__valor">{{ Formato::metrosCubicos($concretagem->volumeDevolvidoEmM3()) }}</strong>
            <span class="indicador__nota">não entrou na forma</span>
        </article>
        <article class="indicador">
            <span class="indicador__rotulo">Corpos de prova</span>
            <strong class="indicador__valor">{{ count($concretagem->corposDeProva()) }}</strong>
            <span class="indicador__nota">
                {{ $emCura }} em cura · {{ count($concretagem->exemplaresDeAceitacao()) }} exemplar(es) de 28 dias
            </span>
        </article>
    </section>

    <h2 class="subtitulo">Cargas recebidas</h2>

    @if ($concretagem->cargas() === [])
        <p class="cartao cartao--vazio">Nenhum caminhão registrado ainda.</p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Nota fiscal</th>
                        <th>Placa</th>
                        <th class="numerico">Volume</th>
                        <th>Saída da usina</th>
                        <th>Chegada</th>
                        <th class="numerico">Transporte</th>
                        <th class="numerico">Abatimento</th>
                        <th>Veredito</th>
                        <th>Observação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($concretagem->cargas() as $carga)
                        <tr class="{{ $carga->foiDevolvida() ? 'linha--devolvida' : '' }}">
                            <td>{{ $carga->numero }}</td>
                            <td class="codigo">{{ $carga->notaFiscal }}</td>
                            <td class="codigo">{{ $carga->placa ?? '—' }}</td>
                            <td class="numerico">{{ Formato::metrosCubicos($carga->volumeEmM3) }}</td>
                            <td>{{ Formato::hora($carga->saidaDaUsina) }}</td>
                            <td>{{ Formato::hora($carga->chegada) }}</td>
                            <td class="numerico">{{ $carga->tempoDeTransporteEmMinutos() }} min</td>
                            <td class="numerico">{{ $carga->abatimentoMedidoEmMm }} mm</td>
                            <td>
                                @if ($carga->foiAceita())
                                    <span class="etiqueta etiqueta--aceito">Aceita</span>
                                @else
                                    <span class="etiqueta etiqueta--nao_conforme">Devolvida</span>
                                    <span class="codigo">{{ $carga->devolucao?->rotulo() }}</span>
                                @endif
                            </td>
                            <td class="miudos">{{ $carga->observacao ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($emAndamento)
        <form class="formulario" method="post"
              action="{{ route('concretagens.cargas', [$obra->codigo, $concretagem->numero()]) }}">
            @csrf

            <fieldset>
                <legend>Receber caminhão</legend>
                <p class="dica">
                    Duas checagens, na ordem do canteiro: o relógio (até
                    {{ Concretagem::TEMPO_MAXIMO_DE_TRANSPORTE_EM_MINUTOS }} min da usina ao canteiro)
                    e o cone (abatimento em {{ $elemento->abatimento->faixa() }}). A primeira que falhar
                    devolve o caminhão, e a devolução fica registrada com o motivo.
                </p>

                <div class="campos">
                    <label>
                        <span>Nota fiscal</span>
                        <input type="text" name="nota_fiscal" maxlength="40" value="{{ old('nota_fiscal') }}" required>
                    </label>
                    <label>
                        <span>Placa</span>
                        <input type="text" name="placa" maxlength="10" placeholder="ABC-1D23" value="{{ old('placa') }}">
                    </label>
                    <label>
                        <span>Volume (m³)</span>
                        <input type="text" inputmode="decimal" name="volume_m3" placeholder="8,0"
                               value="{{ old('volume_m3') }}" required>
                    </label>
                    <label>
                        <span>Saída da usina</span>
                        <input type="time" name="saida" value="{{ old('saida') }}" required>
                    </label>
                    <label>
                        <span>Chegada</span>
                        <input type="time" name="chegada"
                               value="{{ old('chegada', $ehHoje ? $agora->format('H:i') : '') }}" required>
                    </label>
                    <label>
                        <span>Abatimento medido (mm)</span>
                        <input type="number" name="abatimento_mm" min="0" max="300" step="5"
                               value="{{ old('abatimento_mm', $elemento->abatimento->especificadoEmMm) }}" required>
                    </label>
                </div>

                <div class="campos">
                    <label class="campo--largo">
                        <span>Observação (opcional)</span>
                        <input type="text" name="observacao" maxlength="300" placeholder="Bomba, aditivo, temperatura…"
                               value="{{ old('observacao') }}">
                    </label>
                </div>
            </fieldset>

            <div class="acoes-formulario">
                <button type="submit" class="botao botao--primario">Registrar carga</button>
            </div>
        </form>

        @if ($concretagem->cargasAceitas() !== [])
            <form class="formulario" method="post"
                  action="{{ route('concretagens.moldagens', [$obra->codigo, $concretagem->numero()]) }}">
                @csrf

                <fieldset>
                    <legend>Moldar corpos de prova</legend>
                    <p class="dica">
                        Cada idade marcada gera um exemplar: dois cilindros moldados no mesmo ato.
                        Só carga aceita gera corpo de prova, e o de 28 dias é obrigatório para concluir —
                        é ele que entra na aceitação do lote.
                    </p>

                    <div class="campos">
                        <label>
                            <span>Carga</span>
                            <select name="carga" required>
                                @foreach ($concretagem->cargasAceitas() as $carga)
                                    <option value="{{ $carga->numero }}">
                                        Carga {{ $carga->numero }} — NF {{ $carga->notaFiscal }},
                                        chegou {{ Formato::hora($carga->chegada) }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Hora da moldagem</span>
                            <input type="time" name="hora"
                                   value="{{ old('hora', $ehHoje ? $agora->format('H:i') : '') }}" required>
                        </label>
                    </div>

                    <div class="opcoes">
                        @foreach ($idades as $idade)
                            <label class="opcao">
                                <input type="checkbox" name="idades[]" value="{{ $idade->value }}"
                                       @checked($idade->value === 7 || $idade->ehDeAceitacao())>
                                <span>{{ $idade->rotulo() }}{{ $idade->ehDeAceitacao() ? ' (aceitação)' : '' }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="acoes-formulario">
                    <button type="submit" class="botao botao--primario">Moldar</button>
                </div>
            </form>
        @endif
    @endif

    <h2 class="subtitulo">Corpos de prova</h2>

    @if ($concretagem->exemplares() === [])
        <p class="cartao cartao--vazio">Nenhum corpo de prova moldado.</p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Exemplar</th>
                        <th>Corpo de prova</th>
                        <th>Rompimento previsto</th>
                        <th>Janela</th>
                        <th>Situação</th>
                        <th>Resultado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($concretagem->exemplares() as $exemplar)
                        @foreach ($exemplar->corposDeProva() as $cp)
                            @php
                                $curando = $cp->situacao()->aguardaRompimento();
                                $vencido = $cp->estaVencido($agora);
                            @endphp
                            <tr class="{{ $vencido ? 'linha--vencida' : '' }}">
                                @if ($loop->first)
                                    <td rowspan="2">
                                        <strong>{{ $exemplar->identificacao() }}</strong><br>
                                        <span class="codigo">moldado {{ Formato::dataHora($exemplar->moldadoEm) }}</span><br>
                                        <span class="codigo">exemplar: {{ Formato::mpa($exemplar->resistenciaEmMPa()) }}</span>
                                    </td>
                                @endif
                                <td><strong>{{ $cp->identificacao }}</strong></td>
                                <td>{{ Formato::dataHora($cp->rompimentoPrevisto()) }}</td>
                                <td>
                                    {{ Formato::dataHora($cp->inicioDaJanela()) }} a
                                    {{ Formato::dataHora($cp->fimDaJanela()) }}
                                    <span class="codigo">(± {{ Formato::numero($cp->idade->toleranciaEmHoras(), 1) }} h)</span>
                                </td>
                                <td>
                                    @if ($vencido)
                                        <span class="etiqueta etiqueta--nao_conforme">Vencido</span>
                                    @elseif ($curando && $cp->dentroDaJanela($agora))
                                        <span class="etiqueta etiqueta--em_andamento">Na janela</span>
                                    @else
                                        <span class="etiqueta etiqueta--{{ $cp->situacao()->value }}">
                                            {{ $cp->situacao()->rotulo() }}
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if ($cp->foiRompido())
                                        <strong>{{ Formato::mpa($cp->resistenciaEmMPa()) }}</strong><br>
                                        <span class="codigo">
                                            {{ $cp->resultado()?->descricao() }} ·
                                            {{ Formato::dataHora($cp->resultado()->rompidoEm) }}
                                        </span>
                                    @elseif ($cp->motivoDoDescarte() !== null)
                                        <span class="miudos">{{ $cp->motivoDoDescarte() }}</span>
                                    @else
                                        <span class="codigo">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($curando)
                                        <x-acoes-do-corpo-de-prova
                                            :obra="$obra->codigo"
                                            :numero="$concretagem->numero()"
                                            :identificacao="$cp->identificacao"
                                            :voltar="$aqui"
                                            :agora="$agora"
                                            :pode-romper="$cp->dentroDaJanela($agora)"
                                            :motivo="$vencido ? 'Passou da janela de rompimento sem ser rompido' : ''" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
