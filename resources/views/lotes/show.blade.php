@extends('layouts.app')
@use('App\Support\Formato')
@use('Illuminate\Support\Facades\Gate')

@section('titulo', 'Lote nº ' . $lote->numero() . ' — ' . $obra->nome)

@php
    $estimativa = $lote->estimativa();
    $pendentes = $lote->exemplaresPendentes();
    $perdidos = $lote->exemplaresPerdidos();
@endphp

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> ›
        <a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->codigo }}</a> ›
        Lote nº {{ $lote->numero() }}
    </nav>

    <header class="cabecalho">
        <div>
            <h1 class="titulo">{{ $lote->identificacao() }}</h1>
            <p class="cabecalho__nota">{{ $obra->nome }}</p>
            <p class="cabecalho__nota">
                {{ $lote->condicao->rotulo() }} — {{ $lote->condicao->descricao() }}<br>
                {{ $lote->amostragem->rotulo() }} · {{ Formato::metrosCubicos($lote->volumeEmM3()) }}
                em {{ count($lote->concretagens()) }} concretagem(ns)
                · limite de {{ $lote->grupo->volumeMaximoDoLoteEmM3() }} m³
                para {{ mb_strtolower($lote->grupo->rotulo()) }}
            </p>
        </div>

        <div class="cabecalho__acoes">
            <span class="etiqueta etiqueta--{{ $lote->situacao()->value }}">
                {{ $lote->situacao()->rotulo() }}
            </span>

            @if ($lote->julgadoEm() !== null)
                <span class="codigo">julgado em {{ Formato::dataHora($lote->julgadoEm()) }}</span>
            @endif

            @if ($naoConformidade !== null)
                <a class="botao {{ $naoConformidade->estaAberta() ? 'botao--perigo' : '' }}"
                   href="{{ route('nao-conformidades.show', [$obra->codigo, $lote->numero()]) }}">
                    Não conformidade:
                    {{ $naoConformidade->estaAberta()
                        ? 'aberta'
                        : mb_strtolower($naoConformidade->desfecho()?->rotulo() ?? '') }}
                </a>
            @endif

            @if ($lote->podeSerJulgado() && Gate::allows('decidir'))
                <form method="post" action="{{ route('lotes.julgar', [$obra->codigo, $lote->numero()]) }}"
                      onsubmit="return confirm('Julgar o lote nº {{ $lote->numero() }}? A conta da norma é feita com os exemplares atuais e o veredito fica registrado.');">
                    @csrf
                    <button type="submit" class="botao botao--primario">Julgar lote</button>
                </form>
            @endif
        </div>
    </header>

    <section class="painel">
        <article class="indicador">
            <span class="indicador__rotulo">fck de projeto</span>
            <strong class="indicador__valor">{{ Formato::mpa($lote->classe->fck()) }}</strong>
            <span class="indicador__nota">{{ $lote->classe->rotulo() }}</span>
        </article>
        <article class="indicador {{ $estimativa === null ? '' : ($lote->foiAceito() ? 'indicador--ok' : 'indicador--alerta') }}">
            <span class="indicador__rotulo">fck estimado</span>
            <strong class="indicador__valor">{{ Formato::mpa($estimativa?->fckEstimadoEmMPa) }}</strong>
            <span class="indicador__nota">
                @if ($estimativa === null)
                    ainda não julgado
                @else
                    {{ $lote->foiAceito() ? 'atende ao projeto' : 'abaixo do projeto' }}
                @endif
            </span>
        </article>
        <article class="indicador">
            <span class="indicador__rotulo">Exemplares de 28 dias</span>
            <strong class="indicador__valor">
                {{ count($lote->exemplaresComResultado()) }} / {{ count($lote->exemplaresDeAceitacao()) }}
            </strong>
            <span class="indicador__nota">com resultado / moldados</span>
        </article>
        <article class="indicador {{ $pendentes !== [] ? 'indicador--destaque' : '' }}">
            <span class="indicador__rotulo">Pendentes</span>
            <strong class="indicador__valor">{{ count($pendentes) }}</strong>
            <span class="indicador__nota">{{ count($perdidos) }} perdido(s)</span>
        </article>
    </section>

    @if (!$lote->situacao()->foiJulgado() && $pendentes !== [])
        <p class="dica">
            O lote só é julgado com todos os exemplares de 28 dias resolvidos — rompidos ou
            descartados. Julgar com resultado pendente seria escolher quais cilindros contam.
            Aguardando:
            {{ implode(', ', array_map(static fn ($exemplar): string => $exemplar->identificacao(), $pendentes)) }}.
        </p>
    @endif

    <h2 class="subtitulo">Concretagens do lote</h2>

    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Data</th>
                    <th>Peça</th>
                    <th class="numerico">Volume aceito</th>
                    <th class="numerico">Exemplares 28 d</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lote->concretagens() as $concretagem)
                    <tr>
                        <td>
                            <a href="{{ route('concretagens.show', [$obra->codigo, $concretagem->numero()]) }}">
                                nº {{ $concretagem->numero() }}
                            </a>
                        </td>
                        <td>{{ Formato::data($concretagem->data) }}</td>
                        <td>{{ $concretagem->elemento->identificacao() }}</td>
                        <td class="numerico">{{ Formato::metrosCubicos($concretagem->volumeAceitoEmM3()) }}</td>
                        <td class="numerico">{{ count($concretagem->exemplaresDeAceitacao()) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <h2 class="subtitulo">Exemplares de 28 dias</h2>
    <p class="dica">
        A resistência do exemplar é a maior entre os dois corpos de prova (NBR 5739): os dois
        vieram do mesmo concreto, e o que rompeu mais baixo teve defeito de cilindro, não de
        concreto. Exemplar com um cilindro só vale, mas fica anotado como incompleto.
    </p>

    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Concretagem</th>
                    <th>Exemplar</th>
                    <th class="numerico">CP A</th>
                    <th class="numerico">CP B</th>
                    <th class="numerico">Exemplar</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($lote->concretagens() as $concretagem)
                    @foreach ($concretagem->exemplaresDeAceitacao() as $exemplar)
                        <tr>
                            <td>nº {{ $concretagem->numero() }}</td>
                            <td>{{ $exemplar->identificacao() }}</td>
                            <td class="numerico">{{ Formato::mpa($exemplar->primeiro->resistenciaEmMPa()) }}</td>
                            <td class="numerico">{{ Formato::mpa($exemplar->segundo->resistenciaEmMPa()) }}</td>
                            <td class="numerico"><strong>{{ Formato::mpa($exemplar->resistenciaEmMPa()) }}</strong></td>
                            <td>
                                @if ($exemplar->aguardaRompimento())
                                    <span class="etiqueta etiqueta--em_andamento">Aguardando</span>
                                @elseif ($exemplar->estaCompleto())
                                    <span class="etiqueta etiqueta--aceito">Completo</span>
                                @elseif ($exemplar->estaIncompleto())
                                    <span class="etiqueta etiqueta--prazo">Incompleto</span>
                                @else
                                    <span class="etiqueta etiqueta--nao_conforme">Perdido</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>

    @if ($estimativa !== null)
        <h2 class="subtitulo">Memória de cálculo</h2>

        <section class="cartao memoria">
            <p class="memoria__metodo">{{ $estimativa->metodo }}</p>

            <dl class="fatos">
                <div>
                    <dt>Exemplares (n)</dt>
                    <dd>{{ $estimativa->numeroDeExemplares }}</dd>
                </div>
                <div>
                    <dt>Valores ordenados</dt>
                    <dd>
                        @foreach ($estimativa->valoresOrdenados as $indice => $valor)
                            <span class="codigo">f{{ $indice + 1 }}</span>
                            {{ Formato::numero($valor, 1) }}{{ $loop->last ? '' : ' · ' }}
                        @endforeach
                    </dd>
                </div>
                <div>
                    <dt>Menor / média / maior</dt>
                    <dd>
                        {{ Formato::mpa($estimativa->menorExemplar()) }} /
                        {{ Formato::mpa($estimativa->media()) }} /
                        {{ Formato::mpa($estimativa->maiorExemplar()) }}
                    </dd>
                </div>
                <div>
                    <dt>Amostragem e condição</dt>
                    <dd>
                        {{ $estimativa->amostragem->rotulo() }} · {{ $estimativa->condicao->rotulo() }}
                        (linha {{ $estimativa->condicao->linhaDaTabela() }} da tabela de ψ6)
                    </dd>
                </div>
                @if ($estimativa->valorDaFormula !== null)
                    <div>
                        <dt>Fórmula da norma</dt>
                        <dd>{{ Formato::mpa($estimativa->valorDaFormula) }}</dd>
                    </div>
                    <div>
                        <dt>Piso ψ6 × f1</dt>
                        <dd>
                            {{ Formato::numero($estimativa->psi6 ?? 0.0, 2) }} ×
                            {{ Formato::numero($estimativa->menorExemplar(), 1) }}
                            = {{ Formato::mpa($estimativa->pisoDoPsi6) }}
                            @if ($estimativa->pisoPrevaleceu())
                                <span class="etiqueta etiqueta--prazo">o piso prevaleceu</span>
                            @endif
                        </dd>
                    </div>
                @endif
                <div>
                    <dt>fck estimado</dt>
                    <dd>
                        <strong>{{ Formato::mpa($estimativa->fckEstimadoEmMPa) }}</strong>
                        contra fck de projeto {{ Formato::mpa($lote->classe->fck()) }}
                    </dd>
                </div>
                <div>
                    <dt>Veredito</dt>
                    <dd>
                        <span class="etiqueta etiqueta--{{ $lote->situacao()->value }}">
                            {{ $lote->situacao()->rotulo() }}
                        </span>
                    </dd>
                </div>
            </dl>

            <p class="dica">
                As fórmulas, os limiares e a tabela de ψ6 foram transcritos de memória da NBR 12655.
                Antes de uso real, confira cada linha com o texto vigente da norma.
            </p>
        </section>
    @endif
@endsection
