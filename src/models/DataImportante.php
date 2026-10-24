<?php

class DataImportante
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar(): array
    {
        $stmt = $this->pdo->query('SELECT
        di.id,
        di.id_materia,
        m.nome_materia AS materia,
        di.titulo,
        di.descricao,
        di.data_hora
        FROM datas_importantes AS di
        LEFT JOIN materias AS m
        ON di.id_materia = m.id
        WHERE di.data_hora >= NOW()
        ORDER BY di.data_hora ASC');
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarPorMateria(int $materiaId): array
    {
        $stmt = $this->pdo->prepare('SELECT
            di.id,
            di.id_materia,
            m.nome_materia AS materia,
            di.titulo,
            di.descricao,
            di.data_hora
            FROM datas_importantes AS di
            LEFT JOIN materias AS m ON di.id_materia = m.id
            WHERE di.id_materia = :materiaId AND di.data_hora >= NOW()
            ORDER BY di.data_hora ASC');
        $stmt->execute(['materiaId' => $materiaId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function criar(string $titulo, string $desc, string $data_hora, ?int $materiaId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO datas_importantes (id_materia, titulo, descricao, data_hora)
            VALUES (:materiaId, :titulo, :desc, :data_hora)');
        
        return $stmt->execute([
            'materiaId' => $materiaId,
            'titulo' => $titulo,
            'desc' => $desc,
            'data_hora' => $data_hora
        ]);
    }

    public function editar(int $id, string $titulo, string $desc, string $data_hora, ?int $materiaId = null): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE datas_importantes
             SET id_materia = :materiaId, titulo = :titulo, descricao = :desc, data_hora = :data_hora
             WHERE id = :id'
        );
        $stmt->execute([
            'materiaId' => $materiaId,
            'titulo'    => $titulo,
            'desc'      => $desc,
            'data_hora' => $data_hora,
            'id'        => $id
        ]);
            
        return $stmt->rowCount() > 0;
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM datas_importantes WHERE id = :id');
        
        $stmt->execute(['id' => $id]);

        return $stmt->rowCount() > 0;
    }
}