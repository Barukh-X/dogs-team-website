-- Módulo Batalha Ancestral
CREATE TABLE IF NOT EXISTS batalha_projetos (
 id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(30) NOT NULL UNIQUE, nome VARCHAR(100) NOT NULL,
 descricao VARCHAR(255) NULL, cor_fundo CHAR(7) NOT NULL, cor_texto CHAR(7) NOT NULL,
 criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_solicitacoes (
 id INT AUTO_INCREMENT PRIMARY KEY, id_projeto INT NOT NULL, id_usuario INT NOT NULL,
 inscricao ENUM('roteiro','figurino','encenacao') NULL,
 status ENUM('pendente','aprovada','recusada') NOT NULL DEFAULT 'pendente',
 solicitado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, analisado_por INT NULL, analisado_em DATETIME NULL,
 UNIQUE KEY uq_batalha_solicitacao (id_projeto,id_usuario),
 FOREIGN KEY (id_projeto) REFERENCES batalha_projetos(id) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
 FOREIGN KEY (analisado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_membros (
 id INT AUTO_INCREMENT PRIMARY KEY, id_projeto INT NOT NULL, id_usuario INT NOT NULL,
 inscricao ENUM('roteiro','figurino','encenacao') NULL, aprovado_por INT NOT NULL,
 aprovado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batalha_membro (id_projeto,id_usuario),
 FOREIGN KEY (id_projeto) REFERENCES batalha_projetos(id) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE,
 FOREIGN KEY (aprovado_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_documentos (
 id INT AUTO_INCREMENT PRIMARY KEY, id_projeto INT NOT NULL,
 chave ENUM('poema','fontes','roteiro') NOT NULL, conteudo LONGTEXT NOT NULL,
 atualizado_por INT NULL, atualizado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batalha_documento (id_projeto,chave),
 FOREIGN KEY (id_projeto) REFERENCES batalha_projetos(id) ON DELETE CASCADE,
 FOREIGN KEY (atualizado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_historico (
 id BIGINT AUTO_INCREMENT PRIMARY KEY, id_documento INT NOT NULL, versao INT NOT NULL,
 conteudo LONGTEXT NOT NULL, resumo VARCHAR(255) NULL, id_usuario INT NOT NULL,
 criado_em TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batalha_versao (id_documento,versao),
 FOREIGN KEY (id_documento) REFERENCES batalha_documentos(id) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_figurinos (
 id INT AUTO_INCREMENT PRIMARY KEY, id_projeto INT NOT NULL, id_usuario INT NOT NULL,
 arquivo VARCHAR(120) NOT NULL, legenda VARCHAR(255) NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_projeto) REFERENCES batalha_projetos(id) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_figurino_comentarios (
 id INT AUTO_INCREMENT PRIMARY KEY, id_figurino INT NOT NULL, id_usuario INT NOT NULL,
 comentario TEXT NOT NULL, criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
 FOREIGN KEY (id_figurino) REFERENCES batalha_figurinos(id) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS batalha_encenacao (
 id INT AUTO_INCREMENT PRIMARY KEY, id_projeto INT NOT NULL, id_usuario INT NOT NULL,
 papel VARCHAR(150) NOT NULL, falas LONGTEXT NOT NULL,
 atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 UNIQUE KEY uq_batalha_papel_membro (id_projeto,id_usuario),
 FOREIGN KEY (id_projeto) REFERENCES batalha_projetos(id) ON DELETE CASCADE,
 FOREIGN KEY (id_usuario) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO batalha_projetos (codigo,nome,descricao,cor_fundo,cor_texto) VALUES
('hereamuu','HEREAMUU / SÁBIOS','Espaço de memória e saberes ancestrais.','#000080','#FFFFFF'),
('yaimuu','YÃIMUU / SLAM','Poema e fontes de pesquisa.','#8B0000','#FFFFFF'),
('whipala','WHIPALA / BANDEIRA INDÍGENA','Expressões, cores e identidade.','#FFFFFF','#222222'),
('urihinari','URIHINARI / OFERENDA','Roteiro, figurino e encenação.','#2E8B57','#FFFFFF');
