<?php $podeOperar = $usuarioAtual?->papel->podeOperar() ?? false; ?>
<header class="cabecalho">
    <div>
        <h1 class="titulo">Agenda do laboratório</h1>
        <p class="cabecalho__nota">
            <?= e(dataHoraBr($agora)) ?> · o que a prensa precisa romper, e quando.
            A janela de cada idade é a da NBR 5739; fora dela o resultado não vale.
        </p>
    </div>
</header>

<section class="painel">
    <article class="indicador <?= $vencidos !== [] ? 'indicador--alerta' : '' ?>">
        <span class="indicador__rotulo">Vencidos</span>
        <strong class="indicador__valor"><?= e(count($vencidos)) ?></strong>
        <span class="indicador__nota">passaram da janela sem romper</span>
    </article>
    <article class="indicador <?= $naJanela !== [] ? 'indicador--destaque' : '' ?>">
        <span class="indicador__rotulo">Na janela agora</span>
        <strong class="indicador__valor"><?= e(count($naJanela)) ?></strong>
        <span class="indicador__nota">romper hoje</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Próximos <?= e($horizonteEmDias) ?> dias</span>
        <strong class="indicador__valor"><?= e(count($proximos)) ?></strong>
        <span class="indicador__nota">janela ainda fechada</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Em cura</span>
        <strong class="indicador__valor"><?= e($totalEmCura) ?></strong>
        <span class="indicador__nota">corpos de prova na câmara</span>
    </article>
</section>

<?php if ($vencidos !== []): ?>
    <h2 class="subtitulo subtitulo--alerta">Vencidos — perderam a idade</h2>
    <p class="dica">
        A janela fechou e ninguém rompeu. O ensaio já não representa a idade nominal,
        então o sistema não aceita resultado: descarte com o motivo. O exemplar segue
        com o outro cilindro, se ele ainda existir.
    </p>

    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Corpo de prova</th>
                    <th>Concretagem</th>
                    <th>Peça</th>
                    <th>Idade</th>
                    <th>Janela fechou</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vencidos as $item): ?>
                    <tr class="linha--vencida">
                        <td><strong><?= e($item->identificacao) ?></strong><br><span class="codigo">NF <?= e($item->notaFiscal) ?></span></td>
                        <td>
                            <a href="<?= e(caminho('obras', $item->obraCodigo, 'concretagens', $item->concretagemNumero)) ?>">
                                nº <?= e($item->concretagemNumero) ?>
                            </a><br>
                            <span class="codigo"><?= e($item->obraCodigo) ?></span>
                        </td>
                        <td><?= e($item->elementoIdentificacao) ?><br><span class="codigo">fck <?= e($item->fckDeProjeto) ?> MPa</span></td>
                        <td><?= e($item->idade->rotulo()) ?></td>
                        <td class="atraso"><?= e(dataHoraBr($item->fimDaJanela)) ?></td>
                        <td>
                            <?php if ($podeOperar): ?>
                                <?php
                                $base = caminho('obras', $item->obraCodigo, 'concretagens', $item->concretagemNumero, 'corpos-de-prova', $item->identificacao);
                                $voltar = '/';
                                $podeRomper = false;
                                $motivo = 'Passou da janela de rompimento sem ser rompido';
                                require __DIR__ . '/partes/acoes-do-corpo-de-prova.php';
                                ?>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<h2 class="subtitulo">Na janela — romper agora</h2>

<?php if ($naJanela === []): ?>
    <p class="cartao cartao--vazio">Nenhum corpo de prova com a janela aberta neste momento.</p>
<?php else: ?>
    <p class="dica">
        Informe a força que a prensa marcou, em kN. A resistência em MPa é calculada
        pela área do cilindro — por isso o diâmetro importa: errar 10 por 15 cm erra
        o resultado em 2,25 vezes.
    </p>

    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Corpo de prova</th>
                    <th>Concretagem</th>
                    <th>Peça</th>
                    <th>Idade</th>
                    <th>Janela</th>
                    <th>Resultado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($naJanela as $item): ?>
                    <tr>
                        <td><strong><?= e($item->identificacao) ?></strong><br><span class="codigo">NF <?= e($item->notaFiscal) ?></span></td>
                        <td>
                            <a href="<?= e(caminho('obras', $item->obraCodigo, 'concretagens', $item->concretagemNumero)) ?>">
                                nº <?= e($item->concretagemNumero) ?>
                            </a><br>
                            <span class="codigo"><?= e($item->obraCodigo) ?></span>
                        </td>
                        <td><?= e($item->elementoIdentificacao) ?><br><span class="codigo">fck <?= e($item->fckDeProjeto) ?> MPa</span></td>
                        <td><?= e($item->idade->rotulo()) ?><br><span class="codigo">moldado <?= e(dataHoraBr($item->moldadoEm)) ?></span></td>
                        <td>
                            até <strong><?= e(dataHoraBr($item->fimDaJanela)) ?></strong><br>
                            <span class="codigo">previsto <?= e(dataHoraBr($item->rompimentoPrevisto)) ?></span>
                        </td>
                        <td>
                            <?php if ($podeOperar): ?>
                                <?php
                                $base = caminho('obras', $item->obraCodigo, 'concretagens', $item->concretagemNumero, 'corpos-de-prova', $item->identificacao);
                                $voltar = '/';
                                $podeRomper = true;
                                $motivo = '';
                                require __DIR__ . '/partes/acoes-do-corpo-de-prova.php';
                                ?>
                            <?php else: ?>
                                <span class="codigo">registro pelo laboratório</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<h2 class="subtitulo">Próximos <?= e($horizonteEmDias) ?> dias</h2>

<?php if ($proximos === []): ?>
    <p class="cartao cartao--vazio">Nada previsto para os próximos <?= e($horizonteEmDias) ?> dias.</p>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Corpo de prova</th>
                    <th>Concretagem</th>
                    <th>Peça</th>
                    <th>Idade</th>
                    <th>Rompimento previsto</th>
                    <th>Janela abre</th>
                    <th>Quando</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($proximos as $item): ?>
                    <tr>
                        <td><strong><?= e($item->identificacao) ?></strong></td>
                        <td>
                            <a href="<?= e(caminho('obras', $item->obraCodigo, 'concretagens', $item->concretagemNumero)) ?>">
                                nº <?= e($item->concretagemNumero) ?>
                            </a>
                            <span class="codigo"><?= e($item->obraCodigo) ?></span>
                        </td>
                        <td><?= e($item->elementoIdentificacao) ?></td>
                        <td><?= e($item->idade->rotulo()) ?></td>
                        <td><?= e(dataHoraBr($item->rompimentoPrevisto)) ?></td>
                        <td><?= e(dataHoraBr($item->inicioDaJanela)) ?></td>
                        <td><span class="etiqueta etiqueta--prazo"><?= e($item->estado($agora)) ?></span></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
