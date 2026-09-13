CREATE TABLE IF NOT EXISTS obras (
    codigo                TEXT NOT NULL PRIMARY KEY,
    nome                  TEXT NOT NULL,
    cliente               TEXT NOT NULL,
    responsavel_tecnico   TEXT NOT NULL,
    registro_profissional TEXT NOT NULL,
    criado_em             TEXT NOT NULL DEFAULT (datetime('now'))
);


-- A peça a ser concretada, com a especificação de projeto que servirá de
-- referência para julgar cargas e corpos de prova.
CREATE TABLE IF NOT EXISTS elementos (
    obra_codigo         TEXT    NOT NULL
        REFERENCES obras (codigo) ON DELETE CASCADE ON UPDATE CASCADE,
    codigo              TEXT    NOT NULL,
    tipo                TEXT    NOT NULL
        CHECK (tipo IN ('fundacao', 'pilar', 'viga', 'laje', 'parede',
                        'reservatorio', 'piso', 'outro')),
    descricao           TEXT    NOT NULL,
    pavimento           TEXT,

    -- fck em MPa. O CHECK espelha as classes da NBR 8953: acima de 60 só
    -- existem 70, 80, 90 e 100.
    fck                 INTEGER NOT NULL
        CHECK (fck IN (15, 20, 25, 30, 35, 40, 45, 50, 55, 60, 70, 80, 90, 100)),

    abatimento_mm       INTEGER NOT NULL CHECK (abatimento_mm BETWEEN 10 AND 250),
    volume_previsto_m3  REAL    NOT NULL CHECK (volume_previsto_m3 > 0),

    criado_em           TEXT    NOT NULL DEFAULT (datetime('now')),

    PRIMARY KEY (obra_codigo, codigo)
);
