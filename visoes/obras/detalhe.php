<nav class="trilha">
    <a href="/obras">Obras</a> › <?= e($obra->codigo) ?>
</nav>

<header class="cabecalho">
    <div>
        <h1 class="titulo"><?= e($obra->nome) ?></h1>
        <p class="cabecalho__nota"><?= e($obra->cliente) ?></p>
        <p class="cabecalho__nota">
            Responsável técnico: <?= e($obra->responsavelTecnico) ?> · <?= e($obra->registroProfissional) ?>
        </p>
    </div>

    <?php
    $podeOperar = $usuarioAtual?->papel->podeOperar() ?? false;
    $podeDecidir = $usuarioAtual?->papel->podeDecidir() ?? false;
    ?>
    <div class="cabecalho__acoes">
        <?php if ($podeDecidir): ?>
            <a class="botao" href="<?= e(caminho('obras', $obra->codigo, 'elementos', 'novo')) ?>">Novo elemento</a>
        <?php endif ?>
        <?php if ($podeOperar): ?>
            <a class="botao botao--primario" href="<?= e(caminho('obras', $obra->codigo, 'concretagens', 'nova')) ?>">Nova concretagem</a>
        <?php endif ?>
        <?php if ($podeDecidir): ?>
            <a class="botao" href="<?= e(caminho('obras', $obra->codigo, 'lotes', 'novo')) ?>">Formar lote</a>
        <?php endif ?>
    </div>
</header>

<h2 class="subtitulo">Elementos estruturais</h2>

<?php if ($elementos === []): ?>
    <p class="cartao cartao--vazio">Nenhuma peça cadastrada. Cadastre os elementos antes de abrir uma concretagem.</p>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Peça</th>
                    <th>Tipo</th>
                    <th>Classe</th>
                    <th>Abatimento</th>
                    <th class="numerico">Previsto</th>
                    <th class="numerico">Concretado</th>
                    <th class="numerico">Lotes previstos</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($elementos as $elemento): ?>
                    <tr>
                        <td class="codigo"><?= e($elemento->codigo) ?></td>
                        <td>
                            <?= e($elemento->descricao) ?>
                            <?php if ($elemento->pavimento !== null): ?>
                                <span class="codigo">(<?= e($elemento->pavimento) ?>)</span>
                            <?php endif ?>
                        </td>
                        <td><?= e($elemento->tipo->rotulo()) ?></td>
                        <td><strong><?= e($elemento->classe->rotulo()) ?></strong></td>
                        <td><?= e($elemento->abatimento->faixa()) ?></td>
                        <td class="numerico"><?= e(metrosCubicos($elemento->volumePrevistoEmM3)) ?></td>
                        <td class="numerico"><?= e(metrosCubicos($volumePorElemento[$elemento->codigo] ?? 0.0)) ?></td>
                        <td class="numerico"><?= e($elemento->lotesPrevistos()) ?> × <?= e($elemento->tipo->volumeMaximoDoLoteEmM3()) ?> m³</td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<h2 class="subtitulo">Concretagens</h2>

<?php if ($concretagens === []): ?>
    <p class="cartao cartao--vazio">Nenhuma concretagem registrada.</p>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Data</th>
                    <th>Peça</th>
                    <th class="numerico">Cargas</th>
                    <th class="numerico">Aceito</th>
                    <th class="numerico">Devolvido</th>
                    <th class="numerico">Corpos de prova</th>
                    <th>Situação</th>
                    <th>Lote</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($concretagens as $concretagem): ?>
                    <?php $numeroDoLote = $lotePorConcretagem[$concretagem->numero()] ?? null; ?>
                    <tr>
                        <td>
                            <a href="<?= e(caminho('obras', $obra->codigo, 'concretagens', $concretagem->numero())) ?>">
                                nº <?= e($concretagem->numero()) ?>
                            </a>
                        </td>
                        <td><?= e(dataBr($concretagem->data)) ?></td>
                        <td><?= e($concretagem->elemento->identificacao()) ?> · <?= e($concretagem->elemento->classe->rotulo()) ?></td>
                        <td class="numerico"><?= e(count($concretagem->cargas())) ?></td>
                        <td class="numerico"><?= e(metrosCubicos($concretagem->volumeAceitoEmM3())) ?></td>
                        <td class="numerico <?= $concretagem->volumeDevolvidoEmM3() > 0 ? 'atraso' : '' ?>">
                            <?= e(metrosCubicos($concretagem->volumeDevolvidoEmM3())) ?>
                        </td>
                        <td class="numerico"><?= e(count($concretagem->corposDeProva())) ?></td>
                        <td>
                            <span class="etiqueta etiqueta--<?= e($concretagem->situacao()->value) ?>">
                                <?= e($concretagem->situacao()->rotulo()) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($numeroDoLote !== null): ?>
                                <a href="<?= e(caminho('obras', $obra->codigo, 'lotes', $numeroDoLote)) ?>">Lote <?= e($numeroDoLote) ?></a>
                            <?php else: ?>
                                <span class="codigo">—</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?php if ($naoConformidades !== []): ?>
    <h2 class="subtitulo subtitulo--alerta">Não conformidades</h2>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Lote</th>
                    <th>Aberta em</th>
                    <th class="numerico">fck estimado</th>
                    <th class="numerico">fck de projeto</th>
                    <th class="numerico">Providências</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($naoConformidades as $naoConformidade): ?>
                    <tr class="<?= $naoConformidade->estaAberta() ? 'linha--vencida' : '' ?>">
                        <td>
                            <a href="<?= e(caminho('obras', $obra->codigo, 'lotes', $naoConformidade->loteNumero, 'nao-conformidade')) ?>">
                                Lote <?= e($naoConformidade->loteNumero) ?>
                            </a>
                        </td>
                        <td><?= e(dataBr($naoConformidade->abertaEm)) ?></td>
                        <td class="numerico"><?= e(mpa($naoConformidade->fckEstimadoEmMPa)) ?></td>
                        <td class="numerico"><?= e(mpa($naoConformidade->fckDeProjetoEmMPa)) ?></td>
                        <td class="numerico"><?= e(count($naoConformidade->providencias())) ?></td>
                        <td>
                            <?php if ($naoConformidade->estaAberta()): ?>
                                <span class="etiqueta etiqueta--nao_conforme">Aberta</span>
                            <?php else: ?>
                                <span class="etiqueta etiqueta--aceito"><?= e($naoConformidade->desfecho()?->rotulo()) ?></span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<h2 class="subtitulo">Lotes de aceitação</h2>

<?php if ($lotes === []): ?>
    <p class="cartao cartao--vazio">
        Nenhum lote formado. O lote junta concretagens concluídas do mesmo fck e do mesmo
        grupo, e é julgado quando todos os exemplares de 28 dias tiverem sido rompidos.
    </p>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Classe</th>
                    <th>Grupo</th>
                    <th class="numerico">Volume</th>
                    <th class="numerico">Exemplares 28 d</th>
                    <th class="numerico">fck,est</th>
                    <th>Situação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lotes as $lote): ?>
                    <tr>
                        <td>
                            <a href="<?= e(caminho('obras', $obra->codigo, 'lotes', $lote->numero())) ?>">
                                Lote <?= e($lote->numero()) ?>
                            </a>
                        </td>
                        <td><strong><?= e($lote->classe->rotulo()) ?></strong></td>
                        <td><?= e($lote->grupo->rotulo()) ?></td>
                        <td class="numerico"><?= e(metrosCubicos($lote->volumeEmM3())) ?></td>
                        <td class="numerico">
                            <?= e(count($lote->exemplaresComResultado())) ?> com resultado
                            <?php if ($lote->exemplaresPendentes() !== []): ?>
                                · <?= e(count($lote->exemplaresPendentes())) ?> pendente(s)
                            <?php endif ?>
                        </td>
                        <td class="numerico"><?= e(mpa($lote->estimativa()?->fckEstimadoEmMPa)) ?></td>
                        <td>
                            <span class="etiqueta etiqueta--<?= e($lote->situacao()->value) ?>">
                                <?= e($lote->situacao()->rotulo()) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
