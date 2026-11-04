<?php

class Simulado
{
    /** Tipos de questão aceitos. */
    public const TIPOS = ['objetiva', 'discursiva', 'objetiva_justificativa'];
    /** Tipos que possuem alternativas (exatamente uma correta). */
    public const TIPOS_COM_ALTERNATIVAS = ['objetiva', 'objetiva_justificativa'];
    /** Tipos que dependem de correção manual por um administrador. */
    public const TIPOS_CORRECAO_MANUAL = ['discursiva', 'objetiva_justificativa'];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function existeMateria(int $id): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM materias WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return (bool) $stmt->fetchColumn();
    }

    /** Procura uma matéria pelo nome (a collation utf8mb4_unicode_ci ignora maiúsculas e acentos). */
    public function buscarMateriaPorNome(string $nome): ?array
    {
        $nome = trim($nome);
        if ($nome === '') {
            return null;
        }
        $stmt = $this->pdo->prepare('SELECT id, nome_materia FROM materias WHERE nome_materia = :nome LIMIT 1');
        $stmt->execute(['nome' => $nome]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$linha) {
            return null;
        }
        return ['id' => (int) $linha['id'], 'nome_materia' => $linha['nome_materia']];
    }

    public function materiaDaData(int $dataId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id_materia FROM datas_importantes WHERE id = :id');
        $stmt->execute(['id' => $dataId]);
        $valor = $stmt->fetchColumn();
        if ($valor === false) {
            return null;
        }
        return $valor === null ? null : (int) $valor;
    }

    public function existeData(int $dataId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM datas_importantes WHERE id = :id');
        $stmt->execute(['id' => $dataId]);
        return (bool) $stmt->fetchColumn();
    }

    public function criar(
        int $materiaId,
        ?int $dataId,
        int $criadorId,
        string $titulo,
        ?string $descricao,
        ?int $duracaoMinutos
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO simulados
             (id_materia, id_data_importante, id_criador, titulo, descricao, duracao_minutos, status)
             VALUES (:materia, :data, :criador, :titulo, :descricao, :duracao, \'rascunho\')'
        );
        $stmt->execute([
            'materia' => $materiaId,
            'data' => $dataId,
            'criador' => $criadorId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'duracao' => $duracaoMinutos,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function listar(int $usuarioId, bool $admin, ?int $materiaId = null): array
    {
        $sql = 'SELECT s.id, s.id_materia, m.nome_materia AS materia,
                       s.id_data_importante, di.titulo AS data_titulo, di.data_hora AS data_prova,
                       s.id_criador, s.titulo, s.descricao, s.duracao_minutos, s.status, s.criado_em,
                       (SELECT COUNT(*) FROM questoes q WHERE q.id_simulado = s.id) AS quantidade_questoes,
                       (SELECT COUNT(*) FROM questoes q WHERE q.id_simulado = s.id AND q.tipo <> \'objetiva\') AS quantidade_correcao_manual,
                       (SELECT COUNT(*) FROM tentativas t WHERE t.id_simulado = s.id AND t.status = \'concluida\') AS tentativas_concluidas
                FROM simulados s
                JOIN materias m ON m.id = s.id_materia
                LEFT JOIN datas_importantes di ON di.id = s.id_data_importante
                WHERE ';
        $params = [];
        // Somente administradores enxergam rascunhos; os demais usuários veem apenas simulados publicados.
        if ($admin) {
            $sql .= '1 = 1';
        } else {
            $sql .= 's.status = \'publicado\'';
        }

        if ($materiaId !== null) {
            $sql .= ' AND s.id_materia = :materia';
            $params['materia'] = $materiaId;
        }
        $sql .= ' ORDER BY CASE WHEN s.status = \'publicado\' THEN 0 ELSE 1 END, s.criado_em DESC, s.id DESC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->normalizarLista($stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT s.id, s.id_materia, m.nome_materia AS materia,
                    s.id_data_importante, di.titulo AS data_titulo, di.data_hora AS data_prova,
                    di.id_materia AS data_id_materia,
                    s.id_criador, u.nome AS criador, s.titulo, s.descricao,
                    s.duracao_minutos, s.status, s.criado_em, s.atualizado_em,
                    (SELECT COUNT(*) FROM questoes q WHERE q.id_simulado = s.id) AS quantidade_questoes,
                    (SELECT COUNT(*) FROM questoes q WHERE q.id_simulado = s.id AND q.tipo <> \'objetiva\') AS quantidade_correcao_manual,
                    (SELECT COUNT(*) FROM tentativas t WHERE t.id_simulado = s.id AND t.status = \'concluida\') AS tentativas_concluidas,
                    (SELECT COUNT(*) FROM tentativas t WHERE t.id_simulado = s.id) AS total_tentativas
             FROM simulados s
             JOIN materias m ON m.id = s.id_materia
             JOIN usuarios u ON u.id = s.id_criador
             LEFT JOIN datas_importantes di ON di.id = s.id_data_importante
             WHERE s.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$linha) return null;
        return $this->normalizar($linha);
    }

    public function atualizar(
        int $id,
        int $materiaId,
        ?int $dataId,
        string $titulo,
        ?string $descricao,
        ?int $duracaoMinutos
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE simulados SET id_materia = :materia, id_data_importante = :data,
             titulo = :titulo, descricao = :descricao, duracao_minutos = :duracao,
             atualizado_em = CURRENT_TIMESTAMP WHERE id = :id'
        );
        $stmt->execute([
            'materia' => $materiaId,
            'data' => $dataId,
            'titulo' => $titulo,
            'descricao' => $descricao,
            'duracao' => $duracaoMinutos,
            'id' => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function excluir(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM simulados WHERE id = :id');
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function possuiTentativas(int $simuladoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM tentativas WHERE id_simulado = :id LIMIT 1');
        $stmt->execute(['id' => $simuladoId]);
        return (bool) $stmt->fetchColumn();
    }

    public function publicar(int $id): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE simulados SET status = \'publicado\', atualizado_em = CURRENT_TIMESTAMP
             WHERE id = :id AND status = \'rascunho\''
        );
        $stmt->execute(['id' => $id]);
        return $stmt->rowCount() > 0;
    }

    public function resumoQuestoes(int $simuladoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.id, q.tipo,
                    COUNT(a.id) AS total_alternativas,
                    COALESCE(SUM(CASE WHEN a.correta = 1 THEN 1 ELSE 0 END), 0) AS corretas
             FROM questoes q LEFT JOIN alternativas a ON a.id_questao = q.id
             WHERE q.id_simulado = :id
             GROUP BY q.id, q.tipo'
        );
        $stmt->execute(['id' => $simuladoId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cria um simulado em rascunho com todas as questões e alternativas em UMA transação:
     * se qualquer inserção falhar, nada é gravado.
     *
     * @param array<int, array{enunciado: string, tipo: string, ordem: int, pontos: float, explicacao: ?string, alternativas: array<int, array{texto: string, correta: bool}>}> $questoes
     */
    public function criarComQuestoes(
        int $materiaId,
        ?int $dataId,
        int $criadorId,
        string $titulo,
        ?string $descricao,
        ?int $duracaoMinutos,
        array $questoes
    ): int {
        $this->pdo->beginTransaction();
        try {
            $simuladoId = $this->criar($materiaId, $dataId, $criadorId, $titulo, $descricao, $duracaoMinutos);
            $stmt = $this->pdo->prepare(
                'INSERT INTO questoes (id_simulado, enunciado, tipo, ordem, pontos, explicacao)
                 VALUES (:simulado, :enunciado, :tipo, :ordem, :pontos, :explicacao)'
            );
            foreach ($questoes as $questao) {
                $stmt->execute([
                    'simulado' => $simuladoId,
                    'enunciado' => $questao['enunciado'],
                    'tipo' => $questao['tipo'],
                    'ordem' => $questao['ordem'],
                    'pontos' => $questao['pontos'],
                    'explicacao' => $questao['explicacao'],
                ]);
                $this->inserirAlternativas((int) $this->pdo->lastInsertId(), $questao['alternativas']);
            }
            $this->pdo->commit();
            return $simuladoId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function adicionarQuestao(int $simuladoId, string $enunciado, string $tipo, int $ordem, float $pontos, array $alternativas): int
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO questoes (id_simulado, enunciado, tipo, ordem, pontos)
                 VALUES (:simulado, :enunciado, :tipo, :ordem, :pontos)'
            );
            $stmt->execute([
                'simulado' => $simuladoId,
                'enunciado' => $enunciado,
                'tipo' => $tipo,
                'ordem' => $ordem,
                'pontos' => $pontos,
            ]);
            $questaoId = (int) $this->pdo->lastInsertId();
            $this->inserirAlternativas($questaoId, $alternativas);
            $this->pdo->commit();
            return $questaoId;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function proximaOrdem(int $simuladoId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(ordem), 0) + 1 FROM questoes WHERE id_simulado = :id');
        $stmt->execute(['id' => $simuladoId]);
        return (int) $stmt->fetchColumn();
    }

    public function buscarQuestao(int $questaoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.*, s.id_criador, s.status AS status_simulado
             FROM questoes q JOIN simulados s ON s.id = q.id_simulado
             WHERE q.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $questaoId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        return $linha ?: null;
    }

    public function atualizarQuestao(int $questaoId, string $enunciado, string $tipo, int $ordem, float $pontos, array $alternativas): bool
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'UPDATE questoes SET enunciado = :enunciado, tipo = :tipo, ordem = :ordem, pontos = :pontos WHERE id = :id'
            );
            $stmt->execute([
                'enunciado' => $enunciado,
                'tipo' => $tipo,
                'ordem' => $ordem,
                'pontos' => $pontos,
                'id' => $questaoId,
            ]);
            $del = $this->pdo->prepare('DELETE FROM alternativas WHERE id_questao = :id');
            $del->execute(['id' => $questaoId]);
            $this->inserirAlternativas($questaoId, $alternativas);
            $this->pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }

    public function excluirQuestao(int $questaoId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM questoes WHERE id = :id');
        $stmt->execute(['id' => $questaoId]);
        return $stmt->rowCount() > 0;
    }

    private function inserirAlternativas(int $questaoId, array $alternativas): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO alternativas (id_questao, texto, ordem, correta)
             VALUES (:questao, :texto, :ordem, :correta)'
        );
        foreach ($alternativas as $indice => $alternativa) {
            $stmt->execute([
                'questao' => $questaoId,
                'texto' => $alternativa['texto'],
                'ordem' => $indice + 1,
                'correta' => (int) $alternativa['correta'],
            ]);
        }
    }

    public function buscarQuestoes(int $simuladoId, bool $incluirGabarito): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, enunciado, tipo, ordem, pontos' . ($incluirGabarito ? ', explicacao' : '') . ' FROM questoes
             WHERE id_simulado = :id ORDER BY ordem, id'
        );
        $stmt->execute(['id' => $simuladoId]);
        $questoes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($questoes === []) return [];

        $ids = array_map(static fn(array $q): int => (int) $q['id'], $questoes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $colunas = 'id, id_questao, texto, ordem';
        if ($incluirGabarito) $colunas .= ', correta';
        $alternativaStmt = $this->pdo->prepare(
            "SELECT {$colunas} FROM alternativas WHERE id_questao IN ({$placeholders}) ORDER BY ordem, id"
        );
        $alternativaStmt->execute($ids);
        $porQuestao = [];
        foreach ($alternativaStmt->fetchAll(PDO::FETCH_ASSOC) as $alternativa) {
            $idQuestao = (int) $alternativa['id_questao'];
            $item = [
                'id' => (int) $alternativa['id'],
                'texto' => $alternativa['texto'],
                'ordem' => (int) $alternativa['ordem'],
            ];
            if ($incluirGabarito) $item['correta'] = (bool) $alternativa['correta'];
            $porQuestao[$idQuestao][] = $item;
        }

        foreach ($questoes as &$questao) {
            $questao['id'] = (int) $questao['id'];
            $questao['ordem'] = (int) $questao['ordem'];
            $questao['pontos'] = (float) $questao['pontos'];
            $questao['alternativas'] = $porQuestao[$questao['id']] ?? [];
        }
        unset($questao);
        return $questoes;
    }

    public function iniciarTentativa(int $simuladoId, int $usuarioId): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO tentativas (id_simulado, id_usuario, iniciada_em, status)
             VALUES (:simulado, :usuario, NOW(), \'em_andamento\')'
        );
        $stmt->execute(['simulado' => $simuladoId, 'usuario' => $usuarioId]);
        return (int) $this->pdo->lastInsertId();
    }

    public function buscarTentativa(int $tentativaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.id_simulado, t.id_usuario, t.iniciada_em, t.finalizada_em, t.nota, t.status,
                    s.titulo AS simulado_titulo, s.duracao_minutos, s.status AS status_simulado
             FROM tentativas t JOIN simulados s ON s.id = t.id_simulado
             WHERE t.id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $tentativaId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$linha) return null;
        $linha['id'] = (int) $linha['id'];
        $linha['id_simulado'] = (int) $linha['id_simulado'];
        $linha['id_usuario'] = (int) $linha['id_usuario'];
        $linha['nota'] = $linha['nota'] === null ? null : (float) $linha['nota'];
        return $linha;
    }

    public function tentativaExpirada(int $tentativaId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT CASE WHEN s.duracao_minutos IS NOT NULL
                         AND TIMESTAMPDIFF(SECOND, t.iniciada_em, NOW()) >= s.duracao_minutos * 60
                         THEN 1 ELSE 0 END
             FROM tentativas t JOIN simulados s ON s.id = t.id_simulado
             WHERE t.id = :id'
        );
        $stmt->execute(['id' => $tentativaId]);
        return (bool) $stmt->fetchColumn();
    }

    public function salvarResposta(
        int $tentativaId,
        int $questaoId,
        ?int $alternativaId,
        bool $correta,
        ?string $respostaTexto,
        ?string $justificativa,
        string $statusCorrecao
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO respostas_tentativa
               (id_tentativa, id_questao, id_alternativa, correta, resposta_texto, justificativa, status_correcao)
             VALUES (:tentativa, :questao, :alternativa, :correta, :texto, :justificativa, :status)
             ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id), id_alternativa = VALUES(id_alternativa), correta = VALUES(correta),
               resposta_texto = VALUES(resposta_texto), justificativa = VALUES(justificativa),
               status_correcao = VALUES(status_correcao), pontos_obtidos = NULL'
        );
        $stmt->execute([
            'tentativa' => $tentativaId,
            'questao' => $questaoId,
            'alternativa' => $alternativaId,
            'correta' => (int) $correta,
            'texto' => $respostaTexto,
            'justificativa' => $justificativa,
            'status' => $statusCorrecao,
        ]);
        // Com ON DUPLICATE KEY, LAST_INSERT_ID(id) devolve o id da linha existente (as fotos dependem dele).
        return (int) $this->pdo->lastInsertId();
    }

    public function tipoDaQuestao(int $questaoId): ?string
    {
        $stmt = $this->pdo->prepare('SELECT tipo FROM questoes WHERE id = :id');
        $stmt->execute(['id' => $questaoId]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : (string) $valor;
    }

    public function alternativaCorreta(int $questaoId, int $alternativaId): ?bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT correta FROM alternativas WHERE id = :alternativa AND id_questao = :questao LIMIT 1'
        );
        $stmt->execute(['alternativa' => $alternativaId, 'questao' => $questaoId]);
        $valor = $stmt->fetchColumn();
        return $valor === false ? null : (bool) $valor;
    }

    public function questaoPertenceAoSimulado(int $questaoId, int $simuladoId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM questoes WHERE id = :questao AND id_simulado = :simulado');
        $stmt->execute(['questao' => $questaoId, 'simulado' => $simuladoId]);
        return (bool) $stmt->fetchColumn();
    }

    public function obterQuestoesTentativa(int $tentativaId): array
    {
        $tentativa = $this->buscarTentativa($tentativaId);
        if ($tentativa === null) return [];
        $questoes = $this->buscarQuestoes($tentativa['id_simulado'], false);
        $stmt = $this->pdo->prepare(
            'SELECT rt.id, rt.id_questao, rt.id_alternativa, rt.resposta_texto, rt.justificativa,
                    (SELECT COUNT(*) FROM imagens i WHERE i.id_resposta = rt.id) AS quantidade_imagens
             FROM respostas_tentativa rt WHERE rt.id_tentativa = :id'
        );
        $stmt->execute(['id' => $tentativaId]);
        $respondidas = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $respondidas[(int) $r['id_questao']] = $r;
        }
        foreach ($questoes as &$questao) {
            $resposta = $respondidas[$questao['id']] ?? null;
            $questao['alternativa_selecionada'] = ($resposta === null || $resposta['id_alternativa'] === null)
                ? null : (int) $resposta['id_alternativa'];
            $questao['resposta_texto'] = $resposta['resposta_texto'] ?? null;
            $questao['justificativa'] = $resposta['justificativa'] ?? null;
            $questao['id_resposta'] = $resposta === null ? null : (int) $resposta['id'];
            $questao['quantidade_imagens'] = $resposta === null ? 0 : (int) $resposta['quantidade_imagens'];
        }
        unset($questao);
        return $questoes;
    }

    /**
     * Finaliza a tentativa. Questões objetivas são corrigidas na hora; respostas discursivas e
     * justificativas preenchidas ficam pendentes. Se houver pendências, a tentativa passa a
     * "aguardando_correcao" (sem nota); caso contrário, é concluída com a nota calculada.
     */
    public function finalizarTentativa(int $tentativaId): array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare(
                'SELECT t.id, t.status, t.id_simulado
                 FROM tentativas t WHERE t.id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $tentativaId]);
            $tentativa = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tentativa) {
                $this->pdo->rollBack();
                throw new OutOfBoundsException('Tentativa não encontrada.');
            }

            if ($tentativa['status'] === 'em_andamento') {
                // Respostas manuais totalmente em branco valem zero e não entram na fila de correção.
                $vazias = $this->pdo->prepare(
                    'UPDATE respostas_tentativa rt
                     JOIN questoes q ON q.id = rt.id_questao
                     SET rt.status_correcao = \'automatica\', rt.pontos_obtidos = 0
                     WHERE rt.id_tentativa = :tentativa
                       AND q.tipo <> \'objetiva\'
                       AND rt.status_correcao = \'pendente\'
                       AND rt.id_alternativa IS NULL
                       AND (rt.resposta_texto IS NULL OR TRIM(rt.resposta_texto) = \'\')
                       AND (rt.justificativa IS NULL OR TRIM(rt.justificativa) = \'\')
                       AND NOT EXISTS (SELECT 1 FROM imagens i WHERE i.id_resposta = rt.id)'
                );
                $vazias->execute(['tentativa' => $tentativaId]);

                if ($this->contarPendentes($tentativaId) > 0) {
                    $update = $this->pdo->prepare(
                        'UPDATE tentativas SET status = \'aguardando_correcao\', finalizada_em = NOW(), nota = NULL
                         WHERE id = :id'
                    );
                    $update->execute(['id' => $tentativaId]);
                } else {
                    [$total, $obtidos] = $this->pontosDaTentativa($tentativaId, (int) $tentativa['id_simulado']);
                    $update = $this->pdo->prepare(
                        'UPDATE tentativas SET status = \'concluida\', finalizada_em = NOW(), nota = :nota WHERE id = :id'
                    );
                    $update->execute(['nota' => $this->calcularNota($total, $obtidos), 'id' => $tentativaId]);
                }
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $result = $this->buscarTentativa($tentativaId) ?? [];
        [$total, $obtidos] = $this->pontosDaTentativa($tentativaId, (int) $result['id_simulado']);
        $result['pontos_totais'] = $total;
        $result['pontos_obtidos'] = $obtidos;
        $result['pendentes'] = $this->contarPendentes($tentativaId);
        $result['percentual'] = $result['nota'];
        return $result;
    }

    private function contarPendentes(int $tentativaId): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT COUNT(*) FROM respostas_tentativa WHERE id_tentativa = :id AND status_correcao = \'pendente\''
        );
        $stmt->execute(['id' => $tentativaId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: float, 1: float} pontos totais e pontos obtidos confirmados até agora. */
    private function pontosDaTentativa(int $tentativaId, int $simuladoId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT COALESCE(SUM(q.pontos), 0) AS total,
                    COALESCE(SUM(CASE
                        WHEN q.tipo = \'objetiva\' THEN (CASE WHEN rt.correta = 1 THEN q.pontos ELSE 0 END)
                        ELSE COALESCE(rt.pontos_obtidos, 0)
                    END), 0) AS obtidos
             FROM questoes q
             LEFT JOIN respostas_tentativa rt ON rt.id_questao = q.id AND rt.id_tentativa = :tentativa
             WHERE q.id_simulado = :simulado'
        );
        $stmt->execute(['tentativa' => $tentativaId, 'simulado' => $simuladoId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'obtidos' => 0];
        return [(float) $linha['total'], (float) $linha['obtidos']];
    }

    private function calcularNota(float $total, float $obtidos): float
    {
        return $total > 0 ? round(($obtidos / $total) * 100, 2) : 0.0;
    }

    public function resultadoDetalhado(int $tentativaId): array
    {
        $tentativa = $this->buscarTentativa($tentativaId);
        if ($tentativa === null) return [];

        $stmt = $this->pdo->prepare(
            'SELECT q.id AS id_questao, q.enunciado, q.tipo, q.ordem, q.pontos, q.explicacao,
                    rt.id AS id_resposta,
                    (SELECT COUNT(*) FROM imagens i WHERE i.id_resposta = rt.id) AS quantidade_imagens,
                    rt.id_alternativa AS id_alternativa_selecionada,
                    rt.correta AS resposta_correta,
                    rt.resposta_texto, rt.justificativa, rt.status_correcao,
                    rt.pontos_obtidos, rt.comentario_correcao, rt.corrigida_em,
                    escolhida.texto AS alternativa_selecionada,
                    correta.id AS id_alternativa_correta,
                    correta.texto AS alternativa_correta
             FROM questoes q
             LEFT JOIN respostas_tentativa rt ON rt.id_questao = q.id AND rt.id_tentativa = :tentativa
             LEFT JOIN alternativas escolhida ON escolhida.id = rt.id_alternativa
             LEFT JOIN alternativas correta ON correta.id_questao = q.id AND correta.correta = 1
             WHERE q.id_simulado = :simulado
             ORDER BY q.ordem, q.id'
        );
        $stmt->execute(['tentativa' => $tentativaId, 'simulado' => $tentativa['id_simulado']]);
        $questoes = [];
        $pontosTotais = 0.0;
        $pontosObtidos = 0.0;
        $pendentes = 0;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $tipo = (string) $linha['tipo'];
            $manual = in_array($tipo, self::TIPOS_CORRECAO_MANUAL, true);
            $pontos = (float) $linha['pontos'];
            $acertouAlternativa = (bool) ($linha['resposta_correta'] ?? false);

            // Situação da correção: objetivas e respostas em branco são automáticas.
            $situacao = $manual && $linha['status_correcao'] !== null ? (string) $linha['status_correcao'] : 'automatica';
            if ($tipo === 'objetiva') {
                $obtidos = $acertouAlternativa ? $pontos : 0.0;
            } elseif ($situacao === 'pendente') {
                $obtidos = null;
                $pendentes++;
            } else {
                $obtidos = $linha['pontos_obtidos'] === null ? 0.0 : (float) $linha['pontos_obtidos'];
            }
            $pontosTotais += $pontos;
            if ($obtidos !== null) $pontosObtidos += $obtidos;

            $questoes[] = [
                'id' => (int) $linha['id_questao'],
                'enunciado' => $linha['enunciado'],
                'tipo' => $tipo,
                'ordem' => (int) $linha['ordem'],
                'pontos' => $pontos,
                'explicacao' => $linha['explicacao'],
                'id_alternativa_selecionada' => $linha['id_alternativa_selecionada'] === null ? null : (int) $linha['id_alternativa_selecionada'],
                'alternativa_selecionada' => $linha['alternativa_selecionada'],
                'id_alternativa_correta' => $linha['id_alternativa_correta'] === null ? null : (int) $linha['id_alternativa_correta'],
                'alternativa_correta' => $linha['alternativa_correta'],
                // "correta" só é definitiva para questões objetivas; as demais dependem da correção do administrador.
                'correta' => $tipo === 'objetiva' ? $acertouAlternativa : null,
                'acertou_alternativa' => $tipo === 'objetiva_justificativa' ? $acertouAlternativa : null,
                'resposta_texto' => $linha['resposta_texto'],
                'justificativa' => $linha['justificativa'],
                'id_resposta' => $linha['id_resposta'] === null ? null : (int) $linha['id_resposta'],
                'quantidade_imagens' => (int) ($linha['quantidade_imagens'] ?? 0),
                'situacao_correcao' => $situacao,
                'pontos_obtidos' => $obtidos,
                'comentario_correcao' => $linha['comentario_correcao'],
                'corrigida_em' => $linha['corrigida_em'],
            ];
        }
        $tentativa['pontos_totais'] = $pontosTotais;
        $tentativa['pontos_obtidos'] = $pontosObtidos;
        $tentativa['pendentes'] = $pendentes;
        return ['tentativa' => $tentativa, 'questoes' => $questoes];
    }

    // ------------------------------------------------------------------
    // Correção manual (administradores)
    // ------------------------------------------------------------------

    /** Dados da resposta que o administrador pretende corrigir (inclui tipo e pontuação máxima da questão). */
    public function buscarRespostaParaCorrecao(int $tentativaId, int $questaoId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT q.id AS id_questao, q.tipo, q.pontos, rt.status_correcao
             FROM tentativas t
             JOIN questoes q ON q.id_simulado = t.id_simulado AND q.id = :questao
             LEFT JOIN respostas_tentativa rt ON rt.id_tentativa = t.id AND rt.id_questao = q.id
             WHERE t.id = :tentativa LIMIT 1'
        );
        $stmt->execute(['tentativa' => $tentativaId, 'questao' => $questaoId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$linha) return null;
        $linha['pontos'] = (float) $linha['pontos'];
        return $linha;
    }

    /**
     * Registra a correção de uma resposta e, quando não restar nenhuma pendência, conclui a tentativa
     * e grava a nota. Retorna null se a tentativa ainda estiver em andamento.
     */
    public function registrarCorrecao(int $tentativaId, int $questaoId, float $pontos, ?string $comentario, int $adminId): ?array
    {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare('SELECT id_simulado, status FROM tentativas WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $tentativaId]);
            $tentativa = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$tentativa) {
                $this->pdo->rollBack();
                throw new OutOfBoundsException('Tentativa não encontrada.');
            }
            if ($tentativa['status'] === 'em_andamento') {
                $this->pdo->rollBack();
                return null;
            }

            $update = $this->pdo->prepare(
                'UPDATE respostas_tentativa
                 SET status_correcao = \'corrigida\', pontos_obtidos = :pontos, comentario_correcao = :comentario,
                     corrigida_por = :admin, corrigida_em = NOW()
                 WHERE id_tentativa = :tentativa AND id_questao = :questao AND status_correcao IN (\'pendente\', \'corrigida\')'
            );
            $update->execute([
                'pontos' => $pontos,
                'comentario' => $comentario,
                'admin' => $adminId,
                'tentativa' => $tentativaId,
                'questao' => $questaoId,
            ]);

            $pendentes = $this->contarPendentes($tentativaId);
            [$total, $obtidos] = $this->pontosDaTentativa($tentativaId, (int) $tentativa['id_simulado']);
            if ($pendentes === 0) {
                $conclui = $this->pdo->prepare(
                    'UPDATE tentativas SET status = \'concluida\', nota = :nota WHERE id = :id'
                );
                $conclui->execute(['nota' => $this->calcularNota($total, $obtidos), 'id' => $tentativaId]);
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        $atual = $this->buscarTentativa($tentativaId) ?? [];
        return [
            'status' => $atual['status'] ?? null,
            'nota' => $atual['nota'] ?? null,
            'pendentes' => $pendentes,
            'pontos_totais' => $total,
            'pontos_obtidos' => $obtidos,
        ];
    }

    /** Tentativas com questões de correção manual, filtradas por situação (aguardando_correcao | concluida). */
    public function listarCorrecoes(string $status): array
    {
        $ordem = $status === 'aguardando_correcao' ? 'ASC' : 'DESC';
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS id_tentativa, t.id_simulado, s.titulo AS simulado, m.nome_materia AS materia,
                    u.id AS id_usuario, u.nome AS aluno, u.usuario, t.iniciada_em, t.finalizada_em, t.status, t.nota,
                    SUM(CASE WHEN rt.status_correcao = \'pendente\' THEN 1 ELSE 0 END) AS pendentes,
                    SUM(CASE WHEN rt.status_correcao = \'corrigida\' THEN 1 ELSE 0 END) AS corrigidas
             FROM tentativas t
             JOIN simulados s ON s.id = t.id_simulado
             JOIN materias m ON m.id = s.id_materia
             JOIN usuarios u ON u.id = t.id_usuario
             JOIN respostas_tentativa rt ON rt.id_tentativa = t.id AND rt.status_correcao IN (\'pendente\', \'corrigida\')
             WHERE t.status = :status
             GROUP BY t.id, t.id_simulado, s.titulo, m.nome_materia, u.id, u.nome, u.usuario,
                      t.iniciada_em, t.finalizada_em, t.status, t.nota
             ORDER BY t.finalizada_em ' . $ordem . ', t.id ' . $ordem
        );
        $stmt->execute(['status' => $status]);
        return array_map(static function (array $r): array {
            foreach (['id_tentativa', 'id_simulado', 'id_usuario', 'pendentes', 'corrigidas'] as $campo) {
                $r[$campo] = (int) $r[$campo];
            }
            $r['nota'] = $r['nota'] === null ? null : (float) $r['nota'];
            return $r;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function buscarAluno(int $usuarioId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, nome, usuario, isadmin FROM usuarios WHERE id = :id');
        $stmt->execute(['id' => $usuarioId]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$linha) return null;
        $linha['id'] = (int) $linha['id'];
        $linha['isadmin'] = (bool) $linha['isadmin'];
        return $linha;
    }

    // ------------------------------------------------------------------
    // Ranking
    // ------------------------------------------------------------------

    /**
     * Ranking dos estudantes (administradores ficam de fora). Para cada simulado vale a melhor nota
     * entre as tentativas concluídas (já totalmente corrigidas); a pontuação total é a soma dessas notas.
     * Desempate: maior média, mais simulados concluídos e, por fim, ordem alfabética.
     * Privacidade: a resposta traz apenas o nome de exibição; usuário de login e e-mail nunca são expostos.
     */
    public function ranking(?int $materiaId = null): array
    {
        $filtroMateria = $materiaId !== null ? ' AND s.id_materia = :materia' : '';
        $stmt = $this->pdo->prepare(
            'SELECT u.id AS id_usuario, u.nome, p.id AS id_imagem_perfil,
                    COUNT(*) AS simulados_concluidos,
                    SUM(melhor.nota) AS pontuacao_total,
                    AVG(melhor.nota) AS media
             FROM (
                 SELECT t.id_usuario, t.id_simulado, MAX(t.nota) AS nota
                 FROM tentativas t
                 JOIN simulados s ON s.id = t.id_simulado
                 WHERE t.status = \'concluida\' AND t.nota IS NOT NULL' . $filtroMateria . '
                 GROUP BY t.id_usuario, t.id_simulado
             ) melhor
             JOIN usuarios u ON u.id = melhor.id_usuario
             LEFT JOIN imagens p ON p.id_usuario_perfil = u.id
             WHERE u.isadmin = 0
             GROUP BY u.id, u.nome, p.id
             ORDER BY pontuacao_total DESC, media DESC, simulados_concluidos DESC, u.nome ASC, u.id ASC'
        );
        $stmt->execute($materiaId !== null ? ['materia' => $materiaId] : []);

        $lista = [];
        $posicao = 0;
        $anterior = null;
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $indice => $linha) {
            $total = round((float) $linha['pontuacao_total'], 2);
            $media = round((float) $linha['media'], 2);
            // Empates na pontuação total e na média dividem a mesma posição.
            if ($anterior === null || $anterior['total'] !== $total || $anterior['media'] !== $media) {
                $posicao = $indice + 1;
            }
            $anterior = ['total' => $total, 'media' => $media];
            $lista[] = [
                'posicao' => $posicao,
                'id_usuario' => (int) $linha['id_usuario'],
                'nome' => $linha['nome'],
                'id_imagem_perfil' => $linha['id_imagem_perfil'] === null ? null : (int) $linha['id_imagem_perfil'],
                'simulados_concluidos' => (int) $linha['simulados_concluidos'],
                'pontuacao_total' => $total,
                'media' => $media,
            ];
        }
        return $lista;
    }

    public function historico(int $usuarioId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id AS id_tentativa, t.id_simulado, s.titulo AS simulado,
                    m.nome_materia AS materia, t.iniciada_em, t.finalizada_em,
                    t.status, t.nota, s.duracao_minutos
             FROM tentativas t
             JOIN simulados s ON s.id = t.id_simulado
             JOIN materias m ON m.id = s.id_materia
             WHERE t.id_usuario = :usuario
             ORDER BY t.iniciada_em DESC, t.id DESC'
        );
        $stmt->execute(['usuario' => $usuarioId]);
        return array_map(static function (array $r): array {
            $r['id_tentativa'] = (int) $r['id_tentativa'];
            $r['id_simulado'] = (int) $r['id_simulado'];
            $r['nota'] = $r['nota'] === null ? null : (float) $r['nota'];
            return $r;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    private function normalizarLista(array $linhas): array
    {
        foreach ($linhas as &$linha) {
            $linha = $this->normalizar($linha);
        }
        unset($linha);
        return $linhas;
    }

    private function normalizar(array $linha): array
    {
        foreach (['id', 'id_materia', 'id_criador', 'quantidade_questoes', 'quantidade_correcao_manual', 'tentativas_concluidas'] as $campo) {
            if (array_key_exists($campo, $linha) && $linha[$campo] !== null) $linha[$campo] = (int) $linha[$campo];
        }
        foreach (['id_data_importante', 'data_id_materia', 'total_tentativas', 'duracao_minutos'] as $campo) {
            if (array_key_exists($campo, $linha) && $linha[$campo] !== null) $linha[$campo] = (int) $linha[$campo];
        }
        return $linha;
    }
}
