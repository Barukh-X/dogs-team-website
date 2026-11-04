<?php

/**
 * Acesso aos metadados das imagens. Os arquivos são mantidos em storage/uploads,
 * fora do acesso HTTP direto; a leitura é feita por um endpoint autenticado.
 */
class Imagem
{
    private PDO $pdo;

    private const COLUNAS_ALVO = [
        'anotacao' => 'id_anotacao',
        'data_importante' => 'id_data_importante',
        'perfil' => 'id_usuario_perfil',
        'questao' => 'id_questao',
        'resposta' => 'id_resposta',
    ];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function colunaAlvo(string $tipo): string
    {
        if (!isset(self::COLUNAS_ALVO[$tipo])) {
            throw new InvalidArgumentException('Tipo de imagem inválido.');
        }
        return self::COLUNAS_ALVO[$tipo];
    }

    public function buscarRecurso(string $tipo, int $id): ?array
    {
        $sql = match ($tipo) {
            'anotacao' => 'SELECT id, id_usuario, publica FROM anotacoes WHERE id = :id LIMIT 1',
            'data_importante' => 'SELECT id, id_materia FROM datas_importantes WHERE id = :id LIMIT 1',
            'perfil' => 'SELECT id, id AS id_usuario FROM usuarios WHERE id = :id LIMIT 1',
            'questao' => 'SELECT q.id, q.id_simulado, s.id_criador, s.status AS status_simulado
                          FROM questoes q JOIN simulados s ON s.id = q.id_simulado
                          WHERE q.id = :id LIMIT 1',
            // Foto anexada pelo aluno à própria resposta (discursiva ou objetiva com justificativa).
            'resposta' => 'SELECT rt.id, rt.id_tentativa, rt.id_questao, t.id_usuario,
                                  t.status AS status_tentativa, q.tipo AS tipo_questao,
                                  CASE WHEN s.duracao_minutos IS NOT NULL
                                        AND TIMESTAMPDIFF(SECOND, t.iniciada_em, NOW()) >= s.duracao_minutos * 60
                                       THEN 1 ELSE 0 END AS expirada
                           FROM respostas_tentativa rt
                           JOIN tentativas t ON t.id = rt.id_tentativa
                           JOIN questoes q ON q.id = rt.id_questao
                           JOIN simulados s ON s.id = t.id_simulado
                           WHERE rt.id = :id LIMIT 1',
            default => throw new InvalidArgumentException('Tipo de imagem inválido.'),
        };

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        return $linha ?: null;
    }

    /** O usuário já iniciou (em andamento, aguardando correção ou concluída) alguma tentativa do simulado. */
    public function usuarioTemTentativa(int $usuarioId, int $simuladoId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM tentativas WHERE id_usuario = :usuario AND id_simulado = :simulado LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuarioId, 'simulado' => $simuladoId]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * A foto de perfil de um estudante pode ser vista por qualquer usuário autenticado somente se ele
     * aparece no ranking (não é administrador e tem ao menos um simulado concluído).
     */
    public function perfilVisivelNoRanking(int $usuarioId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1
             FROM usuarios u
             JOIN tentativas t ON t.id_usuario = u.id AND t.status = \'concluida\' AND t.nota IS NOT NULL
             WHERE u.id = :usuario AND u.isadmin = 0
             LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuarioId]);
        return (bool) $stmt->fetchColumn();
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM imagens WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        return $linha ?: null;
    }

    public function buscarPerfil(int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM imagens WHERE id_usuario_perfil = :usuario LIMIT 1'
        );
        $stmt->execute(['usuario' => $usuarioId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        return $linha ?: null;
    }

    /**
     * Registra um anexo ou substitui a foto de perfil existente. O chamador
     * controla a transação para também poder desfazer o upload físico.
     */
    public function registrar(
        string $tipo,
        int $recursoId,
        int $usuarioId,
        string $arquivo,
        string $nomeOriginal,
        string $mime,
        int $tamanho
    ): int {
        $coluna = $this->colunaAlvo($tipo);

        if ($tipo === 'perfil') {
            $antiga = $this->buscarPerfil($recursoId);
            if ($antiga !== null) {
                $stmt = $this->pdo->prepare(
                    'UPDATE imagens SET id_usuario = :usuario, arquivo = :arquivo,
                     nome_original = :nome, tipo_mime = :mime, tamanho = :tamanho
                     WHERE id = :id'
                );
                $stmt->execute([
                    'usuario' => $usuarioId,
                    'arquivo' => $arquivo,
                    'nome' => $nomeOriginal,
                    'mime' => $mime,
                    'tamanho' => $tamanho,
                    'id' => (int) $antiga['id'],
                ]);
                return (int) $antiga['id'];
            }
        }

        $sql = "INSERT INTO imagens
                (id_usuario, {$coluna}, arquivo, nome_original, tipo_mime, tamanho)
                VALUES (:usuario, :recurso, :arquivo, :nome, :mime, :tamanho)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'usuario' => $usuarioId,
            'recurso' => $recursoId,
            'arquivo' => $arquivo,
            'nome' => $nomeOriginal,
            'mime' => $mime,
            'tamanho' => $tamanho,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /** Quantidade de imagens já anexadas a um recurso (usada para limitar as fotos por resposta). */
    public function contarPorRecurso(string $tipo, int $recursoId): int
    {
        $coluna = $this->colunaAlvo($tipo);
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM imagens WHERE {$coluna} = :id");
        $stmt->execute(['id' => $recursoId]);
        return (int) $stmt->fetchColumn();
    }

    public function listarPorRecurso(string $tipo, int $recursoId): array
    {
        $coluna = $this->colunaAlvo($tipo);
        $stmt = $this->pdo->prepare(
            "SELECT id, {$coluna} AS id_recurso, nome_original, tipo_mime, tamanho, criado_em
             FROM imagens WHERE {$coluna} = :id ORDER BY criado_em, id"
        );
        $stmt->execute(['id' => $recursoId]);

        return array_map(static function (array $linha): array {
            return [
                'id' => (int) $linha['id'],
                'id_recurso' => (int) $linha['id_recurso'],
                'nome_original' => $linha['nome_original'],
                'tipo_mime' => $linha['tipo_mime'],
                'tamanho' => (int) $linha['tamanho'],
                'criado_em' => $linha['criado_em'],
                'url' => '/api/imagens/arquivo?id=' . (int) $linha['id'],
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM imagens WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /** Registros cujos arquivos precisam ser removidos após excluir o recurso. */
    public function listarArquivosRecurso(string $tipo, int $recursoId): array
    {
        $coluna = $this->colunaAlvo($tipo);
        $stmt = $this->pdo->prepare(
            "SELECT id, arquivo FROM imagens WHERE {$coluna} = :id"
        );
        $stmt->execute(['id' => $recursoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarArquivosDoSimulado(int $simuladoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT i.id, i.arquivo
             FROM imagens i
             JOIN questoes q ON q.id = i.id_questao
             WHERE q.id_simulado = :id'
        );
        $stmt->execute(['id' => $simuladoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obterPdo(): PDO
    {
        return $this->pdo;
    }
}
