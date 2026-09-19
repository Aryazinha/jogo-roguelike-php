-- 001_create_scores.sql
-- Ranking de partidas.  Requisitos: RNF01, RF32, RF33, RF34.
--
-- O banco em si nao e versionado: e binario e gera conflito de merge
-- que nao se resolve.  Versionamos este arquivo, e cada integrante
-- recria o banco local aplicando as migracoes em ordem.

CREATE TABLE IF NOT EXISTS scores (
    id                  INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,

    -- RF33: nome informado pelo jogador ao fim da partida.
    nome_jogador        TEXT    NOT NULL,
    personagem          TEXT    NOT NULL,

    -- RF32: pontuacao calculada como
    -- (nivel * 100) + (inimigos * 10) + (tempo * 2) + (venceu * 500)
    pontuacao           INTEGER NOT NULL,

    nivel               INTEGER NOT NULL,
    inimigos_derrotados INTEGER NOT NULL,

    -- RF30: tempo total de sobrevivencia, em segundos.
    tempo_segundos      INTEGER NOT NULL,

    -- RF09: 1 se o jogador derrotou o boss, 0 caso contrario.
    venceu              INTEGER NOT NULL DEFAULT 0,

    -- Semente do gerador aleatorio, para reproduzir a partida.
    semente             INTEGER NOT NULL,

    criado_em           TEXT    NOT NULL DEFAULT (datetime('now')),

    CHECK (venceu IN (0, 1)),
    CHECK (pontuacao >= 0),
    CHECK (nivel >= 0),
    CHECK (inimigos_derrotados >= 0),
    CHECK (tempo_segundos >= 0),
    CHECK (length(trim(nome_jogador)) > 0)
);

-- RF34: ranking ordenado por pontuacao decrescente.
-- O criterio de desempate definido pela equipe e, nesta ordem:
--   1. maior pontuacao
--   2. menor tempo de sobrevivencia (premia eficiencia)
--   3. partida mais antiga
-- O indice reproduz exatamente essa ordem, entao a consulta do
-- ranking e resolvida so pelo indice.
CREATE INDEX IF NOT EXISTS idx_scores_ranking
    ON scores (pontuacao DESC, tempo_segundos ASC, criado_em ASC);

-- Usado pelo filtro de ranking por personagem.
CREATE INDEX IF NOT EXISTS idx_scores_personagem
    ON scores (personagem, pontuacao DESC);
