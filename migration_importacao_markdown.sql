-- Migração para bancos Dogs Team que já têm o módulo de simulados com tipos de questão
-- (migration_imagens_simulados.sql e migration_tipos_questoes_ranking.sql).
-- Selecione o banco no phpMyAdmin, faça backup e execute este arquivo UMA ÚNICA VEZ,
-- ANTES de enviar os arquivos novos da aplicação.
-- Não apaga tabelas nem dados; questões já existentes ficam com explicação vazia (NULL).
-- Se o phpMyAdmin acusar "Duplicate column name 'explicacao'", a migração já foi aplicada.

-- Explicação (opcional) de cada questão: aparece ao aluno somente depois de finalizar a tentativa.
ALTER TABLE questoes
  ADD COLUMN explicacao MEDIUMTEXT NULL AFTER pontos;
