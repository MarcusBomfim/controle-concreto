@use('App\Dominio\Ensaio\DiametroDoCorpoDeProva')

{{--
    Os formulários de romper e descartar um corpo de prova, usados na agenda
    e na tela da concretagem.

    Na versão em PHP puro isto era um `require` de um arquivo que lia
    variáveis do escopo de quem incluía — $base, $voltar, $podeRomper,
    $motivo, mais $diametros e $agora que tinham que estar lá por acaso.
    Funcionava, mas a "assinatura" só existia num comentário: esquecer de
    definir uma delas dava aviso do PHP em produção.

    Componente anônimo do Blade: @props é a assinatura de verdade, com
    padrões, e o que não for declarado não entra.

    A lista de diâmetros vem do próprio enum do domínio. Ele é um valor puro,
    sem dependência nenhuma, e a alternativa seria passá-lo por três
    controladores só para chegar num <select>.
--}}

@props([
    'obra',
    'numero',
    'identificacao',
    'voltar',
    'agora',
    'podeRomper' => false,
    'motivo' => '',
])

@if ($podeRomper)
    <form class="formulario-linha" method="post"
          action="{{ route('corpos-de-prova.romper', [$obra, $numero, $identificacao]) }}">
        @csrf
        <input type="hidden" name="voltar" value="{{ $voltar }}">
        <label>
            <span>kN</span>
            <input type="text" inputmode="decimal" name="carga_kn" size="6" placeholder="245,5" required>
        </label>
        <label>
            <span>Ø</span>
            <select name="diametro_mm">
                @foreach (DiametroDoCorpoDeProva::cases() as $diametro)
                    <option value="{{ $diametro->value }}">{{ $diametro->rotulo() }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Rompido em</span>
            <input type="datetime-local" name="rompido_em" value="{{ $agora->format('Y-m-d\TH:i') }}" required>
        </label>
        <button type="submit" class="botao botao--primario">Registrar</button>
    </form>
@endif

<details class="descarte">
    <summary>Descartar</summary>
    <form class="formulario-linha" method="post"
          action="{{ route('corpos-de-prova.descartar', [$obra, $numero, $identificacao]) }}">
        @csrf
        <input type="hidden" name="voltar" value="{{ $voltar }}">
        <label class="campo--largo">
            <span>Motivo</span>
            <input type="text" name="motivo" maxlength="300" value="{{ $motivo }}"
                   placeholder="Quebrou na desforma, foi perdido, passou da janela…" required>
        </label>
        <button type="submit" class="botao botao--perigo">Confirmar descarte</button>
    </form>
</details>
