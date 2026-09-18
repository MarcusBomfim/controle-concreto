/*
 * O tratamento do lote reprovado.
 *
 * Uma não conformidade por lote: a chave primária é a mesma do lote, e a
 * chave estrangeira garante que ela só existe para lote que existe. Apagar a
 * obra leva tudo junto, em cascata, como no resto do esquema.
 */
CREATE TABLE IF NOT EXISTS nao_conformidades (
    obra_codigo      TEXT    NOT NULL,
    lote_numero      INTEGER NOT NULL CHECK (lote_numero > 0),

    aberta_em        TEXT    NOT NULL,
    fck_projeto_mpa  REAL    NOT NULL CHECK (fck_projeto_mpa > 0),
    fck_estimado_mpa REAL    NOT NULL CHECK (fck_estimado_mpa >= 0),

    situacao         TEXT    NOT NULL DEFAULT 'aberta'
        CHECK (situacao IN ('aberta', 'encerrada')),

    -- Preenchidos no encerramento, os três juntos.
    desfecho         TEXT    CHECK (desfecho IN ('estrutura_aceita', 'reforcada', 'demolida')),
    parecer          TEXT,
    encerrada_em     TEXT,

    PRIMARY KEY (obra_codigo, lote_numero),
    FOREIGN KEY (obra_codigo, lote_numero)
        REFERENCES lotes (obra_codigo, numero) ON DELETE CASCADE ON UPDATE CASCADE,

    -- Só se abre não conformidade de lote que ficou abaixo do fck.
    CHECK (fck_estimado_mpa < fck_projeto_mpa),

    -- Aberta não tem desfecho; encerrada tem desfecho, parecer e data.
    CHECK (
        (situacao = 'aberta' AND desfecho IS NULL AND parecer IS NULL AND encerrada_em IS NULL)
        OR (situacao = 'encerrada' AND desfecho IS NOT NULL AND parecer IS NOT NULL AND encerrada_em IS NOT NULL)
    )
);

CREATE INDEX IF NOT EXISTS idx_nc_abertas ON nao_conformidades (situacao, aberta_em);


/*
 * Os passos do tratamento, numerados na ordem em que foram registrados.
 * Providência não se edita nem se apaga: o histórico é a prova.
 */
CREATE TABLE IF NOT EXISTS providencias (
    obra_codigo    TEXT    NOT NULL,
    lote_numero    INTEGER NOT NULL,
    numero         INTEGER NOT NULL CHECK (numero > 0),

    tipo           TEXT    NOT NULL CHECK (tipo IN (
        'revisao_de_projeto', 'ensaio_nao_destrutivo', 'extracao_de_testemunhos',
        'prova_de_carga', 'reforco', 'demolicao'
    )),
    realizada_em   TEXT    NOT NULL,
    descricao      TEXT    NOT NULL,
    resultado      TEXT    NOT NULL CHECK (resultado IN ('favoravel', 'desfavoravel', 'informativo')),
    responsavel    TEXT    NOT NULL,

    -- Só a extração de testemunhos devolve um fck medido na peça.
    fck_obtido_mpa REAL    CHECK (fck_obtido_mpa IS NULL OR fck_obtido_mpa > 0),

    PRIMARY KEY (obra_codigo, lote_numero, numero),
    FOREIGN KEY (obra_codigo, lote_numero)
        REFERENCES nao_conformidades (obra_codigo, lote_numero) ON DELETE CASCADE ON UPDATE CASCADE,

    CHECK (
        (tipo = 'extracao_de_testemunhos' AND fck_obtido_mpa IS NOT NULL)
        OR (tipo <> 'extracao_de_testemunhos' AND fck_obtido_mpa IS NULL)
    )
);
