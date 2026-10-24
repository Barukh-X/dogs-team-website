CREATE DATABASE dogs_team_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE dogs_team_db;

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome varchar(30) NOT NULL,
  usuario varchar(30) NOT NULL UNIQUE,
  email varchar(50) NOT NULL UNIQUE,
  senha varchar(255) NOT NULL,
  isadmin BOOLEAN NOT NULL DEFAULT 0
);

CREATE TABLE materias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_materia varchar(30) NOT NULL UNIQUE
);

CREATE TABLE datas_importantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_materia INT,
  titulo varchar(50) NOT NULL,
  descricao TEXT,
  data_hora DATETIME NOT NULL,
  
  FOREIGN KEY (id_materia) REFERENCES materias(id)
);
CREATE TABLE anotacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_usuario INT NOT NULL,
  id_materia INT NOT NULL,
  titulo VARCHAR(100) NOT NULL,
  descricao_anot MEDIUMTEXT,
  publica BOOLEAN NOT NULL DEFAULT 0,

  FOREIGN KEY (id_usuario) REFERENCES usuarios(id)
    ON DELETE CASCADE,
  FOREIGN KEY (id_materia) REFERENCES materias(id)
);

CREATE TABLE anotacao_links (
  id INT AUTO_INCREMENT PRIMARY KEY,
  id_anotacao INT NOT NULL,
  url VARCHAR(500) NOT NULL,
  titulo VARCHAR(100),

  FOREIGN KEY (id_anotacao) REFERENCES anotacoes(id)
    ON DELETE CASCADE
);