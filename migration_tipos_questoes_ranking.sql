-- Migração para bancos Dogs Team que já têm o módulo de simulados (migration_imagens_simulados.sql).
-- Selecione o banco no phpMyAdmin, faça backup e execute este arquivo UMA ÚNICA VEZ.
-- Não apaga tabelas nem dados. Questões e tentativas já existentes passam a ser do tipo "objetiva".
-- Se o phpMyAdmin acusar "Duplicate column name", a migração já foi aplicada; nada mais precisa ser feito.

-- Tipos de questão: objetiva (1 resposta), discursiva e objetiva com justificativa.
ALTER TABLE questoes
  ADD COLUMN tipo ENUM('objetiva', 'discursiva', 'objetiva_justificativa') NOT NULL DEFAULT 'objetiva' AFTER enunciado;

-- Respostas de texto e fila de correção manual.
ALTER TABLE respostas_tentativa
  ADD COLUMN resposta_texto MEDIUMTEXT NULL AFTER correta,
  ADD COLUMN justificativa MEDIUMTEXT NULL AFTER resposta_texto,
  ADD COLUMN status_correcao ENUM('automatica', 'pendente', 'corrigida') NOT NULL DEFAULT 'automatica' AFTER justificativa,
  ADD COLUMN pontos_obtidos DECIMAL(7,2) NULL AFTER status_correcao,
  ADD COLUMN comentario_correcao TEXT NULL AFTER pontos_obtidos,
  ADD COLUMN corrigida_por INT NULL AFTER comentario_correcao,
  ADD COLUMN corrigida_em DATETIME NULL AFTER corrigida_por,
  ADD INDEX idx_respostas_correcao (id_tentativa, status_correcao),
  ADD CONSTRAINT fk_resposta_corretor FOREIGN KEY (corrigida_por)
    REFERENCES usuarios(id) ON DELETE SET NULL;

-- Nova situação da tentativa: finalizada pelo aluno, mas com questões aguardando correção.
ALTER TABLE tentativas
  MODIFY COLUMN status ENUM('em_andamento', 'aguardando_correcao', 'concluida') NOT NULL DEFAULT 'em_andamento',
  ADD INDEX idx_tentativa_ranking (status, id_usuario, id_simulado);
