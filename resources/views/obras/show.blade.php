@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', $obra->nome)

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> › {{ $obra->codigo }}
    </nav>

    <header class="cabecalho">
        <div>
            <h1 class="titulo">{{ $obra->nome }}</h1>
            <p class="cabecalho__nota">{{ $obra->cliente }}</p>
            <p class="cabecalho__nota">
                Responsável técnico: {{ $obra->responsavelTecnico }} · {{ $obra->registroProfissional }}
            </p>
        </div>

        <div class="cabecalho__acoes">
            <a class="botao" href="{{ route('elementos.create', $obra->codigo) }}">Novo elemento</a>
            <a class="botao" href="{{ route('lotes.create', $obra->codigo) }}">Formar lote</a>
            <a class="botao botao--primario" href="{{ route('concretagens.create', $obra->codigo) }}">
                Nova concretagem
            </a>
        </div>
    </header>

    <h2 class="subtitulo">Elementos estruturais</h2>

    @if ($elementos === [])
        <p class="cartao cartao--vazio">
            Nenhuma peça cadastrada. Cadastre os elementos antes de abrir uma concretagem.
        </p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Peça</th>
                        <th>Tipo</th>
                        <th>Classe</th>
                        <th>Abatimento</th>
                        <th class="numerico">Previsto</th>
                        <th class="numerico">Concretado</th>
                        <th class="numerico">Lotes previstos</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($elementos as $elemento)
                        <tr>
                            <td class="codigo">{{ $elemento->codigo }}</td>
                            <td>
                                {{ $elemento->descricao }}
                                @if ($elemento->pavimento !== null)
                                    <span class="codigo">({{ $elemento->pavimento }})</span>
                                @endif
                            </td>
                            <td>{{ $elemento->tipo->rotulo() }}</td>
                            <td><strong>{{ $elemento->classe->rotulo() }}</strong></td>
                            <td>{{ $elemento->abatimento->faixa() }}</td>
                            <td class="numerico">{{ Formato::metrosCubicos($elemento->volumePrevistoEmM3) }}</td>
                            <td class="numerico">
                                {{ Formato::metrosCubicos($volumePorElemento[$elemento->codigo] ?? 0.0) }}
                            </td>
                            <td class="numerico">
                                {{ $elemento->lotesPrevistos() }} × {{ $elemento->tipo->volumeMaximoDoLoteEmM3() }} m³
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="subtitulo">Concretagens</h2>

    @if ($concretagens === [])
        <p class="cartao cartao--vazio">
            Nenhuma concretagem registrada.
            <a href="{{ route('concretagens.create', $obra->codigo) }}">Abra a primeira</a>.
        </p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Data</th>
                        <th>Peça</th>
                        <th class="numerico">Cargas</th>
                        <th class="numerico">Aceito</th>
                        <th class="numerico">Devolvido</th>
                        <th class="numerico">Corpos de prova</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($concretagens as $concretagem)
                        <tr>
                            <td>
                                <a href="{{ route('concretagens.show', [$obra->codigo, $concretagem->numero()]) }}">
                                    nº {{ $concretagem->numero() }}
                                </a>
                            </td>
                            <td>{{ Formato::data($concretagem->data) }}</td>
                            <td>
                                {{ $concretagem->elemento->identificacao() }} ·
                                {{ $concretagem->elemento->classe->rotulo() }}
                            </td>
                            <td class="numerico">{{ count($concretagem->cargas()) }}</td>
                            <td class="numerico">{{ Formato::metrosCubicos($concretagem->volumeAceitoEmM3()) }}</td>
                            <td class="numerico {{ $concretagem->volumeDevolvidoEmM3() > 0 ? 'atraso' : '' }}">
                                {{ Formato::metrosCubicos($concretagem->volumeDevolvidoEmM3()) }}
                            </td>
                            <td class="numerico">{{ count($concretagem->corposDeProva()) }}</td>
                            <td>
                                <span class="etiqueta etiqueta--{{ $concretagem->situacao()->value }}">
                                    {{ $concretagem->situacao()->rotulo() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="subtitulo">Lotes de aceitação</h2>

    @if ($lotes === [])
        <p class="cartao cartao--vazio">
            Nenhum lote formado. O lote é o que a NBR 12655 aceita ou reprova — a concretagem
            sozinha não tem veredito.
        </p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Classe</th>
                        <th>Grupo</th>
                        <th class="numerico">Volume</th>
                        <th class="numerico">Concretagens</th>
                        <th class="numerico">fck estimado</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lotes as $lote)
                        <tr>
                            <td>
                                <a href="{{ route('lotes.show', [$obra->codigo, $lote->numero()]) }}">
                                    nº {{ $lote->numero() }}
                                </a>
                            </td>
                            <td><strong>{{ $lote->classe->rotulo() }}</strong></td>
                            <td>{{ $lote->grupo->rotulo() }}</td>
                            <td class="numerico">{{ Formato::metrosCubicos($lote->volumeEmM3()) }}</td>
                            <td class="numerico">{{ count($lote->concretagens()) }}</td>
                            <td class="numerico">{{ Formato::mpa($lote->estimativa()?->fckEstimadoEmMPa) }}</td>
                            <td>
                                <span class="etiqueta etiqueta--{{ $lote->situacao()->value }}">
                                    {{ $lote->situacao()->rotulo() }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
