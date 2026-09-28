<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo', 'Controle de Concreto') — Controle de Concreto</title>
    <link rel="stylesheet" href="{{ asset('estilo.css') }}">
</head>
<body>
    <header class="topo">
        <a class="topo__marca" href="{{ route('agenda') }}">
            <span class="topo__sigla">CC</span>
            <span>Controle de Concreto</span>
        </a>
        <nav class="topo__menu">
            <a href="{{ route('agenda') }}">Agenda</a>
            <a href="{{ route('obras.index') }}">Obras</a>
        </nav>
        <p class="topo__legenda">Recebimento, corpos de prova e aceitação pela NBR 12655</p>
    </header>

    <main class="pagina">
        {{-- A sessão "flash" do Laravel faz o papel de guardarMensagem() /
             tirarMensagem(): sobrevive ao redirecionamento e some depois. --}}
        @if (session('mensagem'))
            <p class="aviso aviso--ok" role="status">{{ session('mensagem') }}</p>
        @endif

        @if (session('erro'))
            <p class="aviso aviso--erro" role="alert">{{ session('erro') }}</p>
        @endif

        {{-- Erros de validação vindos de um Form Request. --}}
        @if ($errors->any())
            <div class="aviso aviso--erro" role="alert">
                @foreach ($errors->all() as $erro)
                    <p style="margin: 0">{{ $erro }}</p>
                @endforeach
            </div>
        @endif

        @yield('conteudo')
    </main>

    <footer class="rodape">
        <p>
            Controle tecnológico do concreto · valores de norma transcritos de memória —
            confira com o texto vigente antes de uso real · Laravel {{ app()->version() }}
        </p>
    </footer>
</body>
</html>
