@extends('layouts.app')

@section('titulo', 'Entrar')

@section('conteudo')
    <section class="cartao cartao--acesso">
        <h1 class="titulo">Entrar</h1>
        <p class="dica">Use uma das contas de demonstração ou a sua.</p>

        <form class="formulario formulario--acesso" method="post" action="{{ route('acesso.entrar') }}">
            @csrf

            <label>
                <span>E-mail</span>
                <input type="email" name="email" autocomplete="username"
                       value="{{ old('email') }}" required autofocus>
            </label>

            <label>
                <span>Senha</span>
                <input type="password" name="senha" autocomplete="current-password" required>
            </label>

            <button type="submit" class="botao botao--primario">Entrar</button>
        </form>

        <details class="contas-demo">
            <summary>Contas de demonstração</summary>
            <table class="tabela">
                <thead>
                    <tr><th>E-mail</th><th>Senha</th><th>Papel</th></tr>
                </thead>
                <tbody>
                    <tr>
                        <td><code>engenheiro@concreto.dev</code></td>
                        <td><code>Engenheiro@123</code></td>
                        <td>Engenheiro — acesso completo</td>
                    </tr>
                    <tr>
                        <td><code>laboratorio@concreto.dev</code></td>
                        <td><code>Laboratorio@123</code></td>
                        <td>Laboratorista — concretagens e ensaios, sem lote</td>
                    </tr>
                    <tr>
                        <td><code>gestor@concreto.dev</code></td>
                        <td><code>Gestor@123</code></td>
                        <td>Gestor — somente leitura</td>
                    </tr>
                </tbody>
            </table>
            <p class="dica">Criadas por <code>php artisan migrate --seed</code>. Só para desenvolvimento.</p>
        </details>
    </section>
@endsection
