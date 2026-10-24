<?php

class Anotacao
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function listar_anotacoes(int $usuarioId, ?int $materiaId = null): array
    {
        $sql = 'SELECT id, id_usuario, id_materia, titulo, descricao_anot, publica
                FROM anotacoes
                WHERE (publica = 1 OR id_usuario = :usuarioId)';
        $params = ['usuarioId' => $usuarioId];

        if ($materiaId !== null) {
            $sql .= ' AND id_materia = :materiaId';
            $params['materiaId'] = $materiaId;
        }

        $sql .= ' ORDER BY id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $anotacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($anotacoes as &$anotacao) {
            $anotacao['publica'] = (bool) $anotacao['publica'];
        }
        unset($anotacao);

        return $this->anexarLinks($anotacoes);
    }

    public function criar(
        int $usuarioId,
        int $materiaId,
        string $titulo,
        string $descricao,
        bool $publica,
        array $links = []
    ): int {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO anotacoes (id_usuario, id_materia, titulo, descricao_anot, publica)
                 VALUES (:usuarioId, :materiaId, :titulo, :descricao, :publica)'
            );
            $stmt->execute([
                'usuarioId' => $usuarioId,
                'materiaId' => $materiaId,
                'titulo'    => $titulo,
                'descricao' => $descricao,
                'publica'   => (int) $publica,
            ]);

            $id = (int) $this->pdo->lastInsertId();
            $this->inserirLinks($id, $links);

            $this->pdo->commit();

            return $id;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function editar(
        int $id,
        int $usuarioId,
        bool $admin,
        int $materiaId,
        string $titulo,
        string $descricao,
        ?array $links = null
    ): bool {
        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'UPDATE anotacoes
                 SET id_materia = :materiaId, titulo = :titulo, descricao_anot = :descricao
                 WHERE id = :id AND (id_usuario = :usuarioId OR (publica = 1 AND :admin = 1))'
            );
            $stmt->execute([
                'materiaId' => $materiaId,
                'titulo'    => $titulo,
                'descricao' => $descricao,
                'id'        => $id,
                'usuarioId' => $usuarioId,
                'admin'     => (int) $admin,
            ]);

            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return false;
            }

            if ($links !== null) {
                $del = $this->pdo->prepare('DELETE FROM anotacao_links WHERE id_anotacao = :id');
                $del->execute(['id' => $id]);
                $this->inserirLinks($id, $links);
            }

            $this->pdo->commit();

            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    public function excluir(int $id, int $usuarioId, bool $admin): bool
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM anotacoes
             WHERE id = :id AND (id_usuario = :usuarioId OR (publica = 1 AND :admin = 1))'
        );
        $stmt->execute([
            'id'        => $id,
            'usuarioId' => $usuarioId,
            'admin'     => (int) $admin,
        ]);

        return $stmt->rowCount() > 0;
    }

    private function inserirLinks(int $anotacaoId, array $links): void
    {
        if ($links === []) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO anotacao_links (id_anotacao, url, titulo) VALUES (:anotacaoId, :url, :titulo)'
        );

        foreach ($links as $link) {
            $stmt->execute([
                'anotacaoId' => $anotacaoId,
                'url'        => $link['url'],
                'titulo'     => $link['titulo'],
            ]);
        }
    }

    private function anexarLinks(array $anotacoes): array
    {
        foreach ($anotacoes as &$anotacao) {
            $anotacao['links'] = [];
        }
        unset($anotacao);

        if ($anotacoes === []) {
            return $anotacoes;
        }

        $ids = array_column($anotacoes, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $stmt = $this->pdo->prepare(
            "SELECT id, id_anotacao, url, titulo
             FROM anotacao_links
             WHERE id_anotacao IN ($placeholders)
             ORDER BY id"
        );
        $stmt->execute($ids);

        $porAnotacao = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $link) {
            $porAnotacao[$link['id_anotacao']][] = [
                'id'     => $link['id'],
                'url'    => $link['url'],
                'titulo' => $link['titulo'],
            ];
        }

        foreach ($anotacoes as &$anotacao) {
            $anotacao['links'] = $porAnotacao[$anotacao['id']] ?? [];
        }
        unset($anotacao);

        return $anotacoes;
    }
}