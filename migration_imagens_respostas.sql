-- Fotos nas respostas dos simulados (questões discursivas e objetivas com justificativa).
-- Selecione o banco no phpMyAdmin antes de executar. Faça backup antes de aplicar.
-- Não apaga tabelas nem dados. Execute UMA vez (o MySQL não tem ADD COLUMN IF NOT EXISTS em todas as versões).

ALTER TABLE imagens
  ADD COLUMN id_resposta INT NULL AFTER id_questao,
  ADD INDEX idx_imagem_resposta (id_resposta),
  ADD CONSTRAINT fk_imagem_resposta FOREIGN KEY (id_resposta)
    REFERENCES respostas_tentativa(id) ON DELETE CASCADE;
