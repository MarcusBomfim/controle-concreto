@extends('layouts.app')

@section('titulo', 'Novo elemento — ' . $obra->nome)

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> ›
        <a href="{{ route('obras.show', $obra->codigo) }}">{{ $obra->codigo }}</a> ›
        Novo elemento
    </nav>

    <h1 class="titulo">Novo elemento estrutural</h1>
    <p class="cabecalho__nota">{{ $obra->nome }}</p>

    <form class="formulario" method="post" action="{{ route('elementos.store', $obra->codigo) }}">
        @csrf

        <fieldset>
            <legend>A peça</legend>

            <div class="campos">
                <label>
                    <span>Código</span>
                    <input type="text" name="codigo" maxlength="30" placeholder="L3-P4"
                           value="{{ old('codigo') }}" required>
                </label>

                <label>
                    <span>Tipo</span>
                    <select name="tipo" required>
                        @foreach ($tipos as $tipo)
                            <option value="{{ $tipo->value }}" @selected(old('tipo') === $tipo->value)>
                                {{ $tipo->rotulo() }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="campo--largo">
                    <span>Descrição</span>
                    <input type="text" name="descricao" maxlength="200" placeholder="Laje L3"
                           value="{{ old('descricao') }}" required>
                </label>

                <label>
                    <span>Pavimento (opcional)</span>
                    <input type="text" name="pavimento" maxlength="60" placeholder="4º pavimento"
                           value="{{ old('pavimento') }}">
                </label>
            </div>

            <p class="dica">
                O tipo define o grupo de solicitação e, com ele, o tamanho do lote de aceitação:
                pilar e parede fecham lote a cada 50 m³; laje, viga e fundação, a cada 100 m³.
            </p>
        </fieldset>

        <fieldset>
            <legend>Especificação do projeto</legend>

            <div class="campos">
                <label>
                    <span>Classe de resistência</span>
                    <select name="fck" required>
                        @foreach ($classes as $classe)
                            <option value="{{ $classe->value }}"
                                    @selected((int) old('fck', 30) === $classe->value)>
                                {{ $classe->rotulo() }} — {{ $classe->value }} MPa
                            </option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span>Abatimento especificado (mm)</span>
                    <input type="number" name="abatimento_mm" min="10" max="250" step="10"
                           value="{{ old('abatimento_mm', 100) }}" required>
                </label>

                <label>
                    <span>Volume previsto (m³)</span>
                    <input type="text" inputmode="decimal" name="volume_previsto_m3" placeholder="42,0"
                           value="{{ old('volume_previsto_m3') }}" required>
                </label>
            </div>

            <p class="dica">
                Peça estrutural exige no mínimo C20 (NBR 6118). A tolerância do abatimento
                é calculada pela NBR 7212: ±10 mm até 90, ±20 até 150, ±30 acima.
            </p>
        </fieldset>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao--primario">Cadastrar elemento</button>
            <a class="botao" href="{{ route('obras.show', $obra->codigo) }}">Cancelar</a>
        </div>
    </form>
@endsection
