@extends('layouts.app')

@section('titulo', 'Nova obra')

@section('conteudo')
    <nav class="trilha">
        <a href="{{ route('obras.index') }}">Obras</a> › Nova
    </nav>

    <h1 class="titulo">Nova obra</h1>
    <p class="cabecalho__nota">O responsável técnico e o registro são os que vão assinar o laudo.</p>

    <form class="formulario" method="post" action="{{ route('obras.store') }}">
        {{-- @csrf gera o campo oculto com o token da sessão. Na versão em
             PHP puro isto era um input escrito à mão em cada formulário, e
             um hash_equals no controlador. --}}
        @csrf

        <fieldset>
            <legend>Identificação</legend>

            <div class="campos">
                <label>
                    <span>Código</span>
                    {{-- old() traz de volta o que foi digitado quando a
                         validação recusa: sem isso o formulário volta vazio. --}}
                    <input type="text" name="codigo" maxlength="20" placeholder="OBR-2026-008"
                           value="{{ old('codigo') }}" required>
                </label>

                <label class="campo--largo">
                    <span>Nome</span>
                    <input type="text" name="nome" maxlength="160" value="{{ old('nome') }}" required>
                </label>
            </div>

            <div class="campos">
                <label class="campo--largo">
                    <span>Cliente</span>
                    <input type="text" name="cliente" maxlength="160" value="{{ old('cliente') }}" required>
                </label>
            </div>
        </fieldset>

        <fieldset>
            <legend>Responsabilidade técnica</legend>

            <div class="campos">
                <label class="campo--largo">
                    <span>Responsável técnico</span>
                    <input type="text" name="responsavel_tecnico" maxlength="160"
                           value="{{ old('responsavel_tecnico') }}" required>
                </label>

                <label>
                    <span>Registro profissional</span>
                    <input type="text" name="registro_profissional" maxlength="30"
                           placeholder="CREA-SP 123456/D"
                           value="{{ old('registro_profissional') }}" required>
                </label>
            </div>
        </fieldset>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao--primario">Cadastrar obra</button>
            <a class="botao" href="{{ route('obras.index') }}">Cancelar</a>
        </div>
    </form>
@endsection
