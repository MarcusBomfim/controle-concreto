@extends('layouts.app')
@use('App\Support\Formato')

@section('titulo', 'Novo lote — ' . $obra->nome)

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> ›
        <a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->codigo }}</a> ›
        Novo lote
    </nav>

    <h1 class="titulo">Formar lote de aceitação</h1>
    <p class="cabecalho__nota">{{ $obra->nome }}</p>

    @if ($disponiveis === [])
        <p class="cartao cartao--vazio">
            Nenhuma concretagem concluída fora de lote. Só concretagem concluída entra em lote,
            e cada uma entra em um lote só.
        </p>
    @else
        <form class="formulario" method="post" action="{{ route('lotes.store', $obra->codigo) }}">
            @csrf

            <fieldset>
                <legend>Concretagens do lote</legend>
                <p class="dica">
                    A NBR 12655 junta no mesmo lote concreto do mesmo fck e do mesmo grupo
                    (compressão ou flexão), em até 3 dias de concretagem, até 50 m³ para pilares
                    e paredes ou 100 m³ para o resto. A classe e o grupo do lote saem da primeira
                    concretagem marcada; as outras precisam combinar.
                </p>

                <div class="rolagem">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Nº</th>
                                <th>Data</th>
                                <th>Peça</th>
                                <th>Classe</th>
                                <th>Grupo</th>
                                <th class="numerico">Volume aceito</th>
                                <th class="numerico">Exemplares 28 d</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($disponiveis as $concretagem)
                                <tr>
                                    <td>
                                        <input type="checkbox" name="concretagens[]"
                                               value="{{ $concretagem->numero() }}"
                                               @checked(in_array($concretagem->numero(), old('concretagens', []), false))>
                                    </td>
                                    <td>nº {{ $concretagem->numero() }}</td>
                                    <td>{{ Formato::data($concretagem->data) }}</td>
                                    <td>{{ $concretagem->elemento->identificacao() }}</td>
                                    <td><strong>{{ $concretagem->elemento->classe->rotulo() }}</strong></td>
                                    <td>{{ $concretagem->elemento->tipo->grupo()->rotulo() }}</td>
                                    <td class="numerico">{{ Formato::metrosCubicos($concretagem->volumeAceitoEmM3()) }}</td>
                                    <td class="numerico">{{ count($concretagem->exemplaresDeAceitacao()) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </fieldset>

            <fieldset>
                <legend>Como o concreto foi preparado e amostrado</legend>

                <div class="campos">
                    <label class="campo--largo">
                        <span>Condição de preparo</span>
                        <select name="condicao" required>
                            @foreach ($condicoes as $condicao)
                                <option value="{{ $condicao->value }}" @selected(old('condicao') === $condicao->value)>
                                    {{ $condicao->rotulo() }} — {{ $condicao->descricao() }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Amostragem</span>
                        <select name="amostragem" required>
                            @foreach ($amostragens as $amostragem)
                                <option value="{{ $amostragem->value }}" @selected(old('amostragem') === $amostragem->value)>
                                    {{ $amostragem->rotulo() }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <p class="dica">
                    Amostragem total: cada caminhão virou exemplar, e o fck estimado é o menor deles.
                    Parcial: só parte foi ensaiada; a norma extrapola com uma fórmula mais conservadora
                    e exige ao menos 6 exemplares. A condição de preparo define o piso ψ6.
                </p>
            </fieldset>

            <div class="acoes-formulario">
                <button type="submit" class="botao botao--primario">Formar lote</button>
                <a class="botao" href="{{ route('obras.show', $obra->codigo) }}">Cancelar</a>
            </div>
        </form>
    @endif
@endsection
