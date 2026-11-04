-- Migração para banco Dogs Team existente. Selecione o banco no phpMyAdmin antes de executar.
-- Faça backup antes de aplicar. Esta migração não apaga tabelas nem dados existentes.

-- ============================================================
-- Módulo de simulados
-- ============================================================
CREATE TABLE IF NOT EXISTS simulados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_materia INT NOT NULL,
  id_data_importante INT NULL,
  id_criador INT NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  descricao TEXT NULL,
  duracao_minutos SMALLINT UNSIGNED NULL,
  status ENUM('rascunho', 'publicado') NOT NULL DEFAULT 'rascunho',
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL,

  INDEX idx_simulados_materia_status (id_materia, status),
  INDEX idx_simulados_criador (id_criador),
  CONSTRAINT fk_simulado_materia FOREIGN KEY (id_materia)
    REFERENCES materias(id) ON DELETE RESTRICT,
  CONSTRAINT fk_simulado_data FOREIGN KEY (id_data_importante)
    REFERENCES datas_importantes(id) ON DELETE SET NULL,
  CONSTRAINT fk_simulado_criador FOREIGN KEY (id_criador)
    REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS questoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_simulado INT NOT NULL,
  enunciado MEDIUMTEXT NOT NULL,
  ordem INT UNSIGNED NOT NULL,
  pontos DECIMAL(7,2) NOT NULL DEFAULT 1.00,

  UNIQUE KEY uq_questao_ordem (id_simulado, ordem),
  CONSTRAINT fk_questao_simulado FOREIGN KEY (id_simulado)
    REFERENCES simulados(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alternativas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_questao INT NOT NULL,
  texto TEXT NOT NULL,
  ordem SMALLINT UNSIGNED NOT NULL,
  correta TINYINT(1) NOT NULL DEFAULT 0,

  UNIQUE KEY uq_alternativa_ordem (id_questao, ordem),
  UNIQUE KEY uq_alternativa_questao (id, id_questao),
  CONSTRAINT fk_alternativa_questao FOREIGN KEY (id_questao)
    REFERENCES questoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tentativas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_simulado INT NOT NULL,
  id_usuario INT NOT NULL,
  iniciada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  finalizada_em DATETIME NULL,
  nota DECIMAL(7,2) NULL,
  status ENUM('em_andamento', 'concluida') NOT NULL DEFAULT 'em_andamento',

  INDEX idx_tentativa_usuario (id_usuario, iniciada_em),
  INDEX idx_tentativa_simulado (id_simulado, status),
  CONSTRAINT fk_tentativa_simulado FOREIGN KEY (id_simulado)
    REFERENCES simulados(id) ON DELETE RESTRICT,
  CONSTRAINT fk_tentativa_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS respostas_tentativa (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_tentativa INT NOT NULL,
  id_questao INT NOT NULL,
  id_alternativa INT NULL,
  correta TINYINT(1) NOT NULL DEFAULT 0,

  UNIQUE KEY uq_resposta_tentativa_questao (id_tentativa, id_questao),
  INDEX idx_respostas_questao (id_questao),
  CONSTRAINT fk_resposta_tentativa FOREIGN KEY (id_tentativa)
    REFERENCES tentativas(id) ON DELETE RESTRICT,
  CONSTRAINT fk_resposta_questao FOREIGN KEY (id_questao)
    REFERENCES questoes(id) ON DELETE RESTRICT,
  CONSTRAINT fk_resposta_alternativa FOREIGN KEY (id_alternativa, id_questao)
    REFERENCES alternativas(id, id_questao) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Módulo de imagens
-- Os arquivos ficam em storage/uploads, protegidos por .htaccess.
-- Cada imagem aponta para exatamente um recurso; a regra de exclusividade
-- é validada pelo controller para manter compatibilidade com MySQL/MariaDB.
-- ============================================================
CREATE TABLE IF NOT EXISTS imagens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_anotacao INT NULL,
  id_data_importante INT NULL,
  id_usuario_perfil INT NULL,
  id_questao INT NULL,
  arquivo VARCHAR(80) NOT NULL UNIQUE,
  nome_original VARCHAR(255) NOT NULL,
  tipo_mime VARCHAR(40) NOT NULL,
  tamanho INT UNSIGNED NOT NULL,
  criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

  UNIQUE KEY uq_imagem_perfil (id_usuario_perfil),
  INDEX idx_imagem_anotacao (id_anotacao),
  INDEX idx_imagem_data (id_data_importante),
  INDEX idx_imagem_questao (id_questao),
  CONSTRAINT fk_imagem_usuario FOREIGN KEY (id_usuario)
    REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_imagem_anotacao FOREIGN KEY (id_anotacao)
    REFERENCES anotacoes(id) ON DELETE CASCADE,
  CONSTRAINT fk_imagem_data FOREIGN KEY (id_data_importante)
    REFERENCES datas_importantes(id) ON DELETE CASCADE,
  CONSTRAINT fk_imagem_perfil FOREIGN KEY (id_usuario_perfil)
    REFERENCES usuarios(id) ON DELETE CASCADE,
  CONSTRAINT fk_imagem_questao FOREIGN KEY (id_questao)
    REFERENCES questoes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
