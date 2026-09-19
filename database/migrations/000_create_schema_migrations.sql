-- 000_create_schema_migrations.sql
-- Controle de quais migracoes ja foram aplicadas.
-- O Migrator cria esta tabela antes de qualquer outra e a consulta
-- para decidir o que rodar. Migracoes sao aplicadas em ordem de nome
-- de arquivo e nunca sao editadas depois de commitadas: corrigir uma
-- migracao ja aplicada significa criar a proxima.

CREATE TABLE IF NOT EXISTS schema_migrations (
    versao      TEXT    NOT NULL PRIMARY KEY,
    aplicada_em TEXT    NOT NULL DEFAULT (datetime('now'))
);
