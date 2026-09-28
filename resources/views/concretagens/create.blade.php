@extends('layouts.app')

@section('titulo', 'Nova concretagem — ' . $obra->nome)

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> ›
        <a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->codigo }}</a> ›
        Nova concretagem
    </nav>

    <h1 class="titulo">Nova concretagem</h1>
    <p class="cabecalho__nota">{{ $obra->nome }}</p>

    @if ($elementos === [])
        <p class="cartao cartao--vazio">
            Esta obra não tem elementos cadastrados. A concretagem é sempre de uma peça —
            <a href="{{ route('elementos.create', $obra->codigo) }}">cadastre a primeira</a>.
        </p>
    @else
        <form class="formulario" method="post" action="{{ route('concretagens.store', $obra->codigo) }}">
            @csrf

            <fieldset>
                <legend>O que será concretado</legend>

                <div class="campos">
                    <label class="campo--largo">
                        <span>Elemento</span>
                        <select name="elemento" required>
                            @foreach ($elementos as $elemento)
                                <option value="{{ $elemento->codigo }}" @selected(old('elemento') === $elemento->codigo)>
                                    {{ $elemento->identificacao() }} —
                                    {{ $elemento->classe->rotulo() }}, abatimento {{ $elemento->abatimento->faixa() }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Data</span>
                        <input type="date" name="data" value="{{ old('data', $hoje) }}" max="{{ $hoje }}" required>
                    </label>
                </div>

                <p class="dica">
                    A especificação da peça — classe e abatimento — é o que cada caminhão será
                    julgado contra. Ela vem do cadastro do elemento, não se digita aqui.
                </p>
            </fieldset>

            <fieldset>
                <legend>Quem</legend>

                <div class="campos">
                    <label class="campo--largo">
                        <span>Fornecedor do concreto</span>
                        <input type="text" name="fornecedor" maxlength="120" placeholder="Concreteira Litoral"
                               value="{{ old('fornecedor') }}" required>
                    </label>

                    <label class="campo--largo">
                        <span>Responsável pela concretagem</span>
                        <input type="text" name="responsavel" maxlength="160"
                               value="{{ old('responsavel', $obra->responsavelTecnico) }}" required>
                    </label>
                </div>
            </fieldset>

            <div class="acoes-formulario">
                <button type="submit" class="botao botao--primario">Abrir concretagem</button>
                <a class="botao" href="{{ route('obras.show', $obra->codigo) }}">Cancelar</a>
            </div>

            <p class="dica">
                Depois de aberta, a concretagem recebe os caminhões um a um, e os corpos de
                prova são moldados das cargas aceitas. Concluir exige exemplar de 28 dias.
            </p>
        </form>
    @endif
@endsection
