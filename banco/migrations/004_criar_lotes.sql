CREATE TABLE IF NOT EXISTS lotes (
    obra_codigo TEXT    NOT NULL
        REFERENCES obras (codigo) ON DELETE CASCADE ON UPDATE CASCADE,
    numero      INTEGER NOT NULL CHECK (numero > 0),

    fck         INTEGER NOT NULL
        CHECK (fck IN (15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 70, 80, 90, 100)),
    grupo       TEXT    NOT NULL CHECK (grupo IN ('vertical', 'horizontal')),
    condicao    TEXT    NOT NULL CHECK (condicao IN ('a', 'b', 'c')),
    amostragem  TEXT    NOT NULL CHECK (amostragem IN ('parcial', 'total')),

    situacao    TEXT    NOT NULL DEFAULT 'aberto'
        CHECK (situacao IN ('aberto', 'aceito', 'nao_conforme')),

    -- Preenchidos no julgamento. A memória de cálculo vai em JSON: é o
    -- registro de como se chegou ao número, e precisa sobreviver a qualquer
    -- mudança futura na calculadora.
    fck_estimado_mpa   REAL,
    julgado_em         TEXT,
    memoria_de_calculo TEXT,

    criado_em   TEXT    NOT NULL DEFAULT (datetime('now')),

    PRIMARY KEY (obra_codigo, numero),

    -- Julgado tem número e data; aberto não tem.
    CHECK (
        (situacao = 'aberto' AND fck_estimado_mpa IS NULL AND julgado_em IS NULL)
        OR (situacao <> 'aberto' AND fck_estimado_mpa IS NOT NULL AND julgado_em IS NOT NULL)
    )
);


/*
 * Quais concretagens compõem cada lote.
 *
 * A chave primária é (obra, concretagem) — e não (obra, lote, concretagem)
 * — de propósito: uma concretagem entra em UM lote só. Duas pessoas formando
 * lotes ao mesmo tempo com a mesma concretagem passariam por qualquer
 * verificação em PHP; a chave primária não deixa a segunda gravar.
 */
CREATE TABLE IF NOT EXISTS lote_concretagens (
    obra_codigo        TEXT    NOT NULL,
    lote_numero        INTEGER NOT NULL,
    concretagem_numero INTEGER NOT NULL,

    PRIMARY KEY (obra_codigo, concretagem_numero),

    FOREIGN KEY (obra_codigo, lote_numero)
        REFERENCES lotes (obra_codigo, numero) ON DELETE CASCADE,

    FOREIGN KEY (obra_codigo, concretagem_numero)
        REFERENCES concretagens (obra_codigo, numero) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_lote_concretagens_lote
    ON lote_concretagens (obra_codigo, lote_numero);
