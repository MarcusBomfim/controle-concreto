<?php
$aberta = $naoConformidade->estaAberta();
$podeDecidir = $usuarioAtual?->papel->podeDecidir() ?? false;
$aqui = caminho('obras', $obra->codigo, 'lotes', $lote->numero(), 'nao-conformidade');
$possiveis = $naoConformidade->desfechosPossiveis();
?>
<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="<?= e(caminho('obras', $obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    <a href="<?= e(caminho('obras', $obra->codigo, 'lotes', $lote->numero())) ?>">Lote nº <?= e($lote->numero()) ?></a> ›
    Não conformidade
</nav>

<header class="cabecalho">
    <div>
        <h1 class="titulo">Não conformidade · <?= e($lote->identificacao()) ?></h1>
        <p class="cabecalho__nota"><?= e($obra->nome) ?> — aberta em <?= e(dataHoraBr($naoConformidade->abertaEm)) ?></p>
        <p class="cabecalho__nota">
            fck estimado de <strong><?= e(mpa($naoConformidade->fckEstimadoEmMPa)) ?></strong>
            contra <?= e(mpa($naoConformidade->fckDeProjetoEmMPa)) ?> de projeto:
            faltaram <?= e(mpa($naoConformidade->deficitEmMPa())) ?> (<?= e(numeroBr($naoConformidade->deficitPercentual(), 1)) ?> %)
        </p>
    </div>

    <div class="cabecalho__acoes">
        <span class="etiqueta etiqueta--<?= $aberta ? 'nao_conforme' : 'aceito' ?>">
            <?= e($naoConformidade->situacao()->rotulo()) ?>
        </span>
        <?php if (!$aberta): ?>
            <span class="codigo"><?= e($naoConformidade->desfecho()?->rotulo()) ?> · <?= e(dataHoraBr($naoConformidade->encerradaEm())) ?></span>
        <?php endif ?>
    </div>
</header>

<?php if (!$aberta): ?>
    <section class="cartao memoria">
        <h2 class="subtitulo" style="margin-top: 0">Parecer de encerramento</h2>
        <p><strong><?= e($naoConformidade->desfecho()?->rotulo()) ?></strong> — <?= e($naoConformidade->desfecho()?->descricao()) ?></p>
        <p class="memoria__metodo"><?= e($naoConformidade->parecer()) ?></p>
    </section>
<?php endif ?>

<h2 class="subtitulo">Providências</h2>
<p class="dica">
    A ordem da norma vai do mais barato ao mais caro: rever o projeto com o fck obtido;
    se não fechar, medir a resistência real na peça (ensaio não destrutivo para localizar,
    testemunho para medir, prova de carga para comprovar); e só no fim reforçar ou demolir.
</p>

<?php if ($naoConformidade->providencias() === []): ?>
    <p class="cartao cartao--vazio">Nenhuma providência registrada ainda. O lote está reprovado e a peça, sem decisão.</p>
<?php else: ?>
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
                <?php foreach ($naoConformidade->providencias() as $indice => $providencia): ?>
                    <tr>
                        <td><?= e($indice + 1) ?></td>
                        <td><?= e(dataBr($providencia->realizadaEm)) ?></td>
                        <td>
                            <strong><?= e($providencia->tipo->rotulo()) ?></strong>
                            <?php if ($providencia->fckObtidoEmMPa !== null): ?>
                                <br><span class="codigo">fck obtido: <?= e(mpa($providencia->fckObtidoEmMPa)) ?></span>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php $classe = match ($providencia->resultado->value) { 'favoravel' => 'aceito', 'desfavoravel' => 'nao_conforme', default => 'prazo' }; ?>
                            <span class="etiqueta etiqueta--<?= e($classe) ?>"><?= e($providencia->resultado->rotulo()) ?></span>
                        </td>
                        <td class="miudos"><?= e($providencia->descricao) ?></td>
                        <td><?= e($providencia->responsavel) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?php if ($aberta && $podeDecidir): ?>
    <form class="formulario" method="post" action="<?= e($aqui) ?>/providencias">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <fieldset>
            <legend>Registrar providência</legend>

            <div class="campos">
                <label class="campo--largo">
                    <span>Tipo</span>
                    <select name="tipo" required>
                        <?php foreach ($tipos as $tipoDeProvidencia): ?>
                            <option value="<?= e($tipoDeProvidencia->value) ?>"><?= e($tipoDeProvidencia->rotulo()) ?> — <?= e($tipoDeProvidencia->descricao()) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label>
                    <span>Realizada em</span>
                    <input type="date" name="realizada_em" value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" required>
                </label>
                <label>
                    <span>Resultado</span>
                    <select name="resultado" required>
                        <?php foreach ($resultados as $resultado): ?>
                            <option value="<?= e($resultado->value) ?>"><?= e($resultado->rotulo()) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
                <label>
                    <span>fck obtido (MPa) — só testemunhos</span>
                    <input type="text" inputmode="decimal" name="fck_obtido_mpa" placeholder="27,5">
                </label>
            </div>

            <div class="campos">
                <label class="campo--largo">
                    <span>Descrição (o que foi feito, onde, o que se concluiu)</span>
                    <input type="text" name="descricao" maxlength="2000" required>
                </label>
                <label class="campo--largo">
                    <span>Responsável</span>
                    <input type="text" name="responsavel" maxlength="160" value="<?= e($usuarioAtual?->nome ?? '') ?>" required>
                </label>
            </div>
        </fieldset>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao--primario">Registrar providência</button>
        </div>
    </form>

    <form class="formulario" method="post" action="<?= e($aqui) ?>/encerrar"
          onsubmit="return confirm('Encerrar a não conformidade? O desfecho e o parecer ficam registrados em definitivo.');">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <fieldset>
            <legend>Encerrar</legend>

            <?php if ($possiveis === []): ?>
                <p class="dica">
                    Nenhum desfecho está sustentado ainda. Aceitar a estrutura exige revisão de projeto,
                    testemunho ou prova de carga com resultado favorável; reforço e demolição exigem a
                    providência correspondente executada.
                </p>
            <?php else: ?>
                <div class="campos">
                    <label class="campo--largo">
                        <span>Desfecho</span>
                        <select name="desfecho" required>
                            <?php foreach ($possiveis as $desfecho): ?>
                                <option value="<?= e($desfecho->value) ?>"><?= e($desfecho->rotulo()) ?> — <?= e($desfecho->descricao()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>
                </div>
                <div class="campos">
                    <label class="campo--largo">
                        <span>Parecer</span>
                        <input type="text" name="parecer" maxlength="2000" required
                               placeholder="Com base nas providências acima, a peça…">
                    </label>
                </div>

                <div class="acoes-formulario">
                    <button type="submit" class="botao botao--perigo">Encerrar não conformidade</button>
                </div>
            <?php endif ?>
        </fieldset>
    </form>
<?php elseif ($aberta): ?>
    <p class="dica">O tratamento da não conformidade é registrado pelo engenheiro responsável.</p>
<?php endif ?>
