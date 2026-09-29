@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', 'Agenda do laboratório')

@section('conteudo')
    <header class="cabecalho">
        <div>
            <h1 class="titulo">Agenda do laboratório</h1>
            <p class="cabecalho__nota">
                {{ Formato::dataHora($agora) }} · o que a prensa precisa romper, e quando.
                A janela de cada idade é a da NBR 5739; fora dela o resultado não vale.
            </p>
        </div>
    </header>

    <section class="painel">
        <article class="indicador {{ $vencidos !== [] ? 'indicador--alerta' : '' }}">
            <span class="indicador__rotulo">Vencidos</span>
            <strong class="indicador__valor">{{ count($vencidos) }}</strong>
            <span class="indicador__nota">passaram da janela sem romper</span>
        </article>
        <article class="indicador {{ $naJanela !== [] ? 'indicador--destaque' : '' }}">
            <span class="indicador__rotulo">Na janela agora</span>
            <strong class="indicador__valor">{{ count($naJanela) }}</strong>
            <span class="indicador__nota">romper hoje</span>
        </article>
        <article class="indicador">
            <span class="indicador__rotulo">Próximos {{ $horizonteEmDias }} dias</span>
            <strong class="indicador__valor">{{ count($proximos) }}</strong>
            <span class="indicador__nota">janela ainda fechada</span>
        </article>
        <article class="indicador">
            <span class="indicador__rotulo">Em cura</span>
            <strong class="indicador__valor">{{ $totalEmCura }}</strong>
            <span class="indicador__nota">corpos de prova na câmara</span>
        </article>
    </section>

    @if ($vencidos !== [])
        <h2 class="subtitulo subtitulo--alerta">Vencidos — perderam a idade</h2>
        <p class="dica">
            A janela fechou e ninguém rompeu. O ensaio já não representa a idade nominal,
            então o sistema não aceita resultado: descarte com o motivo. O exemplar segue
            com o outro cilindro, se ele ainda existir.
        </p>

        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Corpo de prova</th>
                        <th>Concretagem</th>
                        <th>Peça</th>
                        <th>Idade</th>
                        <th>Janela fechou</th>
                        <th>Ação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($vencidos as $item)
                        <tr class="linha--vencida">
                            <td>
                                <strong>{{ $item->identificacao }}</strong><br>
                                <span class="codigo">NF {{ $item->notaFiscal }}</span>
                            </td>
                            <td>
                                <a href="{{ route('concretagens.show', [$item->obraCodigo, $item->concretagemNumero]) }}">
                                    nº {{ $item->concretagemNumero }}
                                </a><br>
                                <span class="codigo">{{ $item->obraCodigo }}</span>
                            </td>
                            <td>
                                {{ $item->elementoIdentificacao }}<br>
                                <span class="codigo">fck {{ $item->fckDeProjeto }} MPa</span>
                            </td>
                            <td>{{ $item->idade->rotulo() }}</td>
                            <td class="atraso">{{ Formato::dataHora($item->fimDaJanela) }}</td>
                            <td>
                                @can('operar')
                                <x-acoes-do-corpo-de-prova
                                    :obra="$item->obraCodigo"
                                    :numero="$item->concretagemNumero"
                                    :identificacao="$item->identificacao"
                                    voltar="/"
                                    :agora="$agora"
                                    :pode-romper="false"
                                    motivo="Passou da janela de rompimento sem ser rompido" />
                                @else
                                    <span class="codigo">descarte pelo laboratório</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="subtitulo">Na janela — romper agora</h2>

    @if ($naJanela === [])
        <p class="cartao cartao--vazio">Nenhum corpo de prova com a janela aberta neste momento.</p>
    @else
        <p class="dica">
            Informe a força que a prensa marcou, em kN. A resistência em MPa é calculada
            pela área do cilindro — por isso o diâmetro importa: errar 10 por 15 cm erra
            o resultado em 2,25 vezes.
        </p>

        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Corpo de prova</th>
                        <th>Concretagem</th>
                        <th>Peça</th>
                        <th>Idade</th>
                        <th>Janela</th>
                        <th>Resultado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($naJanela as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->identificacao }}</strong><br>
                                <span class="codigo">NF {{ $item->notaFiscal }}</span>
                            </td>
                            <td>
                                <a href="{{ route('concretagens.show', [$item->obraCodigo, $item->concretagemNumero]) }}">
                                    nº {{ $item->concretagemNumero }}
                                </a><br>
                                <span class="codigo">{{ $item->obraCodigo }}</span>
                            </td>
                            <td>
                                {{ $item->elementoIdentificacao }}<br>
                                <span class="codigo">fck {{ $item->fckDeProjeto }} MPa</span>
                            </td>
                            <td>
                                {{ $item->idade->rotulo() }}<br>
                                <span class="codigo">moldado {{ Formato::dataHora($item->moldadoEm) }}</span>
                            </td>
                            <td>
                                até <strong>{{ Formato::dataHora($item->fimDaJanela) }}</strong><br>
                                <span class="codigo">previsto {{ Formato::dataHora($item->rompimentoPrevisto) }}</span>
                            </td>
                            <td>
                                @can('operar')
                                    <x-acoes-do-corpo-de-prova
                                        :obra="$item->obraCodigo"
                                        :numero="$item->concretagemNumero"
                                        :identificacao="$item->identificacao"
                                        voltar="/"
                                        :agora="$agora"
                                        :pode-romper="true" />
                                @else
                                    <span class="codigo">registro pelo laboratório</span>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="subtitulo">Próximos {{ $horizonteEmDias }} dias</h2>

    @if ($proximos === [])
        <p class="cartao cartao--vazio">Nada previsto para os próximos {{ $horizonteEmDias }} dias.</p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Corpo de prova</th>
                        <th>Concretagem</th>
                        <th>Peça</th>
                        <th>Idade</th>
                        <th>Rompimento previsto</th>
                        <th>Janela abre</th>
                        <th>Quando</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($proximos as $item)
                        <tr>
                            <td><strong>{{ $item->identificacao }}</strong></td>
                            <td>
                                <a href="{{ route('concretagens.show', [$item->obraCodigo, $item->concretagemNumero]) }}">
                                    nº {{ $item->concretagemNumero }}
                                </a>
                                <span class="codigo">{{ $item->obraCodigo }}</span>
                            </td>
                            <td>{{ $item->elementoIdentificacao }}</td>
                            <td>{{ $item->idade->rotulo() }}</td>
                            <td>{{ Formato::dataHora($item->rompimentoPrevisto) }}</td>
                            <td>{{ Formato::dataHora($item->inicioDaJanela) }}</td>
                            <td><span class="etiqueta etiqueta--prazo">{{ $item->estado($agora) }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
