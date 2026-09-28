@extends('layouts.app')

@section('titulo', 'Obras')

@section('conteudo')
    <header class="cabecalho">
        <div>
            <h1 class="titulo">Obras</h1>
            <p class="cabecalho__nota">Cada obra tem suas peças, suas concretagens e seus lotes de aceitação.</p>
        </div>
        <div class="cabecalho__acoes">
            <a class="botao botao--primario" href="{{ route('obras.create') }}">Nova obra</a>
        </div>
    </header>

    @if ($linhas === [])
        <p class="cartao cartao--vazio">
            Nenhuma obra cadastrada. Cadastre a primeira, ou rode
            <code>php artisan db:seed</code> para carregar a obra de demonstração.
        </p>
    @else
        <div class="grade-obras">
            @foreach ($linhas as $linha)
                @php($obra = $linha['obra'])
                <article class="cartao cartao--obra">
                    <span class="codigo">{{ $obra->codigo }}</span>
                    <h2><a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->nome }}</a></h2>
                    <p class="cartao__cliente">{{ $obra->cliente }}</p>
                    <p class="cartao__responsavel">
                        RT: {{ $obra->responsavelTecnico }} · {{ $obra->registroProfissional }}
                    </p>

                    <dl class="fatos">
                        <div>
                            <dt>Elementos</dt>
                            <dd>{{ $linha['elementos'] }}</dd>
                        </div>
                        <div>
                            <dt>Concretagens</dt>
                            <dd>
                                {{ $linha['concretagens'] }}
                                @if ($linha['emAndamento'] > 0)
                                    <span class="etiqueta etiqueta--em_andamento">
                                        {{ $linha['emAndamento'] }} em andamento
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt>Lotes</dt>
                            <dd>{{ $linha['lotes'] }}</dd>
                        </div>
                    </dl>
                </article>
            @endforeach
        </div>
    @endif
@endsection
