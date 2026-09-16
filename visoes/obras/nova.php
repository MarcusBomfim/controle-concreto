<nav class="trilha">
    <a href="/obras">Obras</a> › Nova
</nav>

<h1 class="titulo">Nova obra</h1>
<p class="cabecalho__nota">O responsável técnico e o registro são os que vão assinar o laudo.</p>

<form class="formulario" method="post" action="/obras">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <fieldset>
        <legend>Identificação</legend>

        <div class="campos">
            <label>
                <span>Código</span>
                <input type="text" name="codigo" maxlength="20" placeholder="OBR-2026-008" required>
            </label>

            <label class="campo--largo">
                <span>Nome</span>
                <input type="text" name="nome" maxlength="160" required>
            </label>
        </div>

        <div class="campos">
            <label class="campo--largo">
                <span>Cliente</span>
                <input type="text" name="cliente" maxlength="160" required>
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>Responsabilidade técnica</legend>

        <div class="campos">
            <label class="campo--largo">
                <span>Responsável técnico</span>
                <input type="text" name="responsavel_tecnico" maxlength="160" required>
            </label>

            <label>
                <span>Registro profissional</span>
                <input type="text" name="registro_profissional" maxlength="30" placeholder="CREA-SP 123456/D" required>
            </label>
        </div>
    </fieldset>

    <div class="acoes-formulario">
        <button type="submit" class="botao botao--primario">Cadastrar obra</button>
        <a class="botao" href="/obras">Cancelar</a>
    </div>
</form>
