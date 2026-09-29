@extends('layouts.app')
@use('App\Support\Formato')
@use('Illuminate\Support\Facades\Gate')

@section('titulo', 'Não conformidade do lote ' . $lote->numero() . ' — ' . $obra->nome)

@php
    $aberta = $naoConformidade->estaAberta();
    $possiveis = $naoConformidade->desfechosPossiveis();
@endphp

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> ›
        <a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->codigo }}</a> ›
        <a href="{{ route('lotes.show', [$obra->codigo, $lote->numero()]) }}">Lote nº {{ $lote->numero() }}</a> ›
        Não conformidade
    </nav>

    <header class="cabecalho">
        <div>
            <h1 class="titulo">Não conformidade · {{ $lote->identificacao() }}</h1>
            <p class="cabecalho__nota">
                {{ $obra->nome }} — aberta em {{ Formato::dataHora($naoConformidade->abertaEm) }}
            </p>
            <p class="cabecalho__nota">
                fck estimado de <strong>{{ Formato::mpa($naoConformidade->fckEstimadoEmMPa) }}</strong>
                contra {{ Formato::mpa($naoConformidade->fckDeProjetoEmMPa) }} de projeto:
                faltaram {{ Formato::mpa($naoConformidade->deficitEmMPa()) }}
                ({{ Formato::numero($naoConformidade->deficitPercentual(), 1) }} %)
            </p>
        </div>

        <div class="cabecalho__acoes">
            <span class="etiqueta etiqueta--{{ $aberta ? 'nao_conforme' : 'aceito' }}">
                {{ $naoConformidade->situacao()->rotulo() }}
            </span>
            @if (!$aberta)
                <span class="codigo">
                    {{ $naoConformidade->desfecho()?->rotulo() }} ·
                    {{ Formato::dataHora($naoConformidade->encerradaEm()) }}
                </span>
            @endif
        </div>
    </header>

    @if (!$aberta)
        <section class="cartao memoria">
            <h2 class="subtitulo" style="margin-top: 0">Parecer de encerramento</h2>
            <p>
                <strong>{{ $naoConformidade->desfecho()?->rotulo() }}</strong> —
                {{ $naoConformidade->desfecho()?->descricao() }}
            </p>
            <p class="memoria__metodo">{{ $naoConformidade->parecer() }}</p>
        </section>
    @endif

    <h2 class="subtitulo">Providências</h2>
    <p class="dica">
        A ordem da norma vai do mais barato ao mais caro: rever o projeto com o fck obtido;
        se não fechar, medir a resistência real na peça (ensaio não destrutivo para localizar,
        testemunho para medir, prova de carga para comprovar); e só no fim reforçar ou demolir.
    </p>

    @if ($naoConformidade->providencias() === [])
        <p class="cartao cartao--vazio">
            Nenhuma providência registrada ainda. O lote está reprovado e a peça, sem decisão.
        </p>
    @else
        <div class="rolagem">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Nº</th>
                        <th>Data</th>
                        <th>Providência</th>
                        <th>Resultado</th>
                        <th>Descrição</th>
                        <th>Responsável</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($naoConformidade->providencias() as $providencia)
                        <tr>
                            <td>{{ $loop->iteration }}</td>
                            <td>{{ Formato::data($providencia->realizadaEm) }}</td>
                            <td>
                                <strong>{{ $providencia->tipo->rotulo() }}</strong>
                                @if ($providencia->fckObtidoEmMPa !== null)
                                    <br><span class="codigo">
                                        fck obtido: {{ Formato::mpa($providencia->fckObtidoEmMPa) }}
                                    </span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $classe = match ($providencia->resultado->value) {
                                        'favoravel' => 'aceito',
                                        'desfavoravel' => 'nao_conforme',
                                        default => 'prazo',
                                    };
                                @endphp
                                <span class="etiqueta etiqueta--{{ $classe }}">
                                    {{ $providencia->resultado->rotulo() }}
                                </span>
                            </td>
                            <td class="miudos">{{ $providencia->descricao }}</td>
                            <td>{{ $providencia->responsavel }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($aberta && Gate::denies('decidir'))
        <p class="dica">O tratamento da não conformidade é registrado pelo engenheiro responsável.</p>
    @endif

    @if ($aberta && Gate::allows('decidir'))
        <form class="formulario" method="post"
              action="{{ route('nao-conformidades.providencias', [$obra->codigo, $lote->numero()]) }}">
            @csrf

            <fieldset>
                <legend>Registrar providência</legend>

                <div class="campos">
                    <label class="campo--largo">
                        <span>Tipo</span>
                        <select name="tipo" required>
                            @foreach ($tipos as $tipoDeProvidencia)
                                <option value="{{ $tipoDeProvidencia->value }}"
                                        @selected(old('tipo') === $tipoDeProvidencia->value)>
                                    {{ $tipoDeProvidencia->rotulo() }} — {{ $tipoDeProvidencia->descricao() }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Realizada em</span>
                        <input type="date" name="realizada_em" value="{{ old('realizada_em', $hoje) }}"
                               max="{{ $hoje }}" required>
                    </label>
                    <label>
                        <span>Resultado</span>
                        <select name="resultado" required>
                            @foreach ($resultados as $resultado)
                                <option value="{{ $resultado->value }}" @selected(old('resultado') === $resultado->value)>
                                    {{ $resultado->rotulo() }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>fck obtido (MPa) — só testemunhos</span>
                        <input type="text" inputmode="decimal" name="fck_obtido_mpa" placeholder="27,5"
                               value="{{ old('fck_obtido_mpa') }}">
                    </label>
                </div>

                <div class="campos">
                    <label class="campo--largo">
                        <span>Descrição (o que foi feito, onde, o que se concluiu)</span>
                        <input type="text" name="descricao" maxlength="2000" value="{{ old('descricao') }}" required>
                    </label>
                    <label class="campo--largo">
                        <span>Responsável</span>
                        <input type="text" name="responsavel" maxlength="160"
                               value="{{ old('responsavel', $obra->responsavelTecnico) }}" required>
                    </label>
                </div>
            </fieldset>

            <div class="acoes-formulario">
                <button type="submit" class="botao botao--primario">Registrar providência</button>
            </div>
        </form>

        <form class="formulario" method="post"
              action="{{ route('nao-conformidades.encerrar', [$obra->codigo, $lote->numero()]) }}"
              onsubmit="return confirm('Encerrar a não conformidade? O desfecho e o parecer ficam registrados em definitivo.');">
            @csrf

            <fieldset>
                <legend>Encerrar</legend>

                @if ($possiveis === [])
                    <p class="dica">
                        Nenhum desfecho está sustentado ainda. Aceitar a estrutura exige revisão de projeto,
                        testemunho ou prova de carga com resultado favorável; reforço e demolição exigem a
                        providência correspondente executada.
                    </p>
                @else
                    <div class="campos">
                        <label class="campo--largo">
                            <span>Desfecho</span>
                            <select name="desfecho" required>
                                @foreach ($possiveis as $desfecho)
                                    <option value="{{ $desfecho->value }}">
                                        {{ $desfecho->rotulo() }} — {{ $desfecho->descricao() }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                    <div class="campos">
                        <label class="campo--largo">
                            <span>Parecer</span>
                            <input type="text" name="parecer" maxlength="2000" required
                                   value="{{ old('parecer') }}"
                                   placeholder="Com base nas providências acima, a peça…">
                        </label>
                    </div>

                    <div class="acoes-formulario">
                        <button type="submit" class="botao botao--perigo">Encerrar não conformidade</button>
                    </div>
                @endif
            </fieldset>
        </form>
    @endif
@endsection
