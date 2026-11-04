<?php

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../models/Simulado.php';
require_once __DIR__ . '/../models/Imagem.php';
require_once __DIR__ . '/UploadImagemService.php';

class SimuladoApiException extends RuntimeException
{
    public int $httpStatus;

    public function __construct(string $mensagem, int $httpStatus = 400)
    {
        parent::__construct($mensagem);
        $this->httpStatus = $httpStatus;
    }
}

/** Regras de negócio do módulo de simulados e tentativas. */
class SimuladoService
{
    private Simulado $modelo;
    private Imagem $imagemModelo;
    private UploadImagemService $upload;

    public function __construct(PDO $pdo)
    {
        $this->modelo = new Simulado($pdo);
        $this->imagemModelo = new Imagem($pdo);
        $this->upload = new UploadImagemService();
    }

    public function listar(int $usuarioId, bool $admin, mixed $materiaInformada = null): array
    {
        $materiaId = null;
        if ($materiaInformada !== null && $materiaInformada !== '') {
            $materiaId = idValido($materiaInformada);
            if ($materiaId === null) throw new SimuladoApiException('id_materia inválido.');
        }
        return $this->modelo->listar($usuarioId, $admin, $materiaId);
    }

    public function detalhes(int $id, int $usuarioId, bool $admin): array
    {
        // As questões só são visíveis para administradores; estudantes as veem apenas depois de iniciar
        // uma tentativa (rota de questões da tentativa) ou ao consultar o resultado.
        $this->exigirAdmin($admin);
        $simulado = $this->obterSimulado($id);
        $simulado['questoes'] = $this->modelo->buscarQuestoes($id, $simulado['status'] === 'rascunho');
        unset($simulado['data_id_materia'], $simulado['total_tentativas']);
        return $simulado;
    }

    public function criar(array $dados, int $usuarioId, bool $admin): int
    {
        $this->exigirAdmin($admin);
        [$materiaId, $dataId, $titulo, $descricao, $duracao] = $this->validarDadosGerais($dados);
        return $this->modelo->criar($materiaId, $dataId, $usuarioId, $titulo, $descricao, $duracao);
    }

    public function editar(array $dados, int $usuarioId, bool $admin): void
    {
        $this->exigirAdmin($admin);
        $id = idValido($dados['id'] ?? null);
        if ($id === null) throw new SimuladoApiException('ID do simulado inválido.');
        $simulado = $this->obterSimulado($id);
        $this->exigirGerencia($simulado, $usuarioId, $admin);
        $this->exigirRascunhoSemTentativas($simulado);

        [$materiaId, $dataId, $titulo, $descricao, $duracao] = $this->validarDadosGerais($dados);
        $this->modelo->atualizar($id, $materiaId, $dataId, $titulo, $descricao, $duracao);
    }

    public function excluir(int $id, int $usuarioId, bool $admin): void
    {
        $this->exigirAdmin($admin);
        $simulado = $this->obterSimulado($id);
        $this->exigirGerencia($simulado, $usuarioId, $admin);
        $this->exigirRascunhoSemTentativas($simulado);
        $arquivos = $this->imagemModelo->listarArquivosDoSimulado($id);
        if (!$this->modelo->excluir($id)) {
            throw new SimuladoApiException('Não foi possível excluir o simulado.', 409);
        }
        $this->upload->removerLista($arquivos);
    }

    public function publicar(int $id, int $usuarioId, bool $admin): void
    {
        $this->exigirAdmin($admin);
        $simulado = $this->obterSimulado($id);
        $this->exigirGerencia($simulado, $usuarioId, $admin);
        if ($simulado['status'] !== 'rascunho') {
            throw new SimuladoApiException('Somente simulados em rascunho podem ser publicados.', 409);
        }

        $questoes = $this->modelo->resumoQuestoes($id);
        if ($questoes === []) {
            throw new SimuladoApiException('Adicione pelo menos uma questão antes de publicar.', 422);
        }
        foreach ($questoes as $questao) {
            // Questões discursivas não têm alternativas; as demais precisam de 2+ alternativas e uma correta.
            if (in_array($questao['tipo'], Simulado::TIPOS_COM_ALTERNATIVAS, true)
                && ((int) $questao['total_alternativas'] < 2 || (int) $questao['corretas'] !== 1)) {
                throw new SimuladoApiException('Cada questão objetiva deve ter pelo menos duas alternativas e exatamente uma correta.', 422);
            }
        }
        if (!$this->modelo->publicar($id)) {
            throw new SimuladoApiException('O simulado não pôde ser publicado.', 409);
        }
    }

    public function adicionarQuestao(array $dados, int $usuarioId, bool $admin): int
    {
        $this->exigirAdmin($admin);
        $simuladoId = idValido($dados['id_simulado'] ?? null);
        if ($simuladoId === null) throw new SimuladoApiException('id_simulado obrigatório e inválido.');
        $simulado = $this->obterSimulado($simuladoId);
        $this->exigirGerencia($simulado, $usuarioId, $admin);
        $this->exigirRascunhoSemTentativas($simulado);

        [$enunciado, $tipo, $ordem, $pontos, $alternativas] = $this->validarQuestao($dados, $simuladoId, null);
        try {
            return $this->modelo->adicionarQuestao($simuladoId, $enunciado, $tipo, $ordem, $pontos, $alternativas);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new SimuladoApiException('Já existe uma questão com essa ordem no simulado.', 409);
            }
            throw $e;
        }
    }

    public function editarQuestao(array $dados, int $usuarioId, bool $admin): void
    {
        $this->exigirAdmin($admin);
        $questaoId = idValido($dados['id'] ?? null);
        if ($questaoId === null) throw new SimuladoApiException('ID da questão inválido.');
        $questao = $this->modelo->buscarQuestao($questaoId);
        if ($questao === null) throw new SimuladoApiException('Questão não encontrada.', 404);
        $simulado = $this->obterSimulado((int) $questao['id_simulado']);
        $this->exigirGerencia($simulado, $usuarioId, $admin);
        $this->exigirRascunhoSemTentativas($simulado);

        [$enunciado, $tipo, $ordem, $pontos, $alternativas] = $this->validarQuestao($dados, (int) $simulado['id'], $questaoId);
        try {
            $this->modelo->atualizarQuestao($questaoId, $enunciado, $tipo, $ordem, $pontos, $alternativas);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                throw new SimuladoApiException('Já existe uma questão com essa ordem no simulado.', 409);
            }
            throw $e;
        }
    }

    public function excluirQuestao(int $questaoId, int $usuarioId, bool $admin): void
    {
        $this->exigirAdmin($admin);
        $questao = $this->modelo->buscarQuestao($questaoId);
        if ($questao === null) throw new SimuladoApiException('Questão não encontrada.', 404);
        $simulado = $this->obterSimulado((int) $questao['id_simulado']);
        $this->exigirGerencia($simulado, $usuarioId, $admin);
        $this->exigirRascunhoSemTentativas($simulado);

        $arquivos = $this->imagemModelo->listarArquivosRecurso('questao', $questaoId);
        if (!$this->modelo->excluirQuestao($questaoId)) {
            throw new SimuladoApiException('Não foi possível excluir a questão.', 409);
        }
        $this->upload->removerLista($arquivos);
    }

    public function iniciar(int $simuladoId, int $usuarioId): int
    {
        $simulado = $this->obterSimulado($simuladoId);
        if ($simulado['status'] !== 'publicado') {
            throw new SimuladoApiException('Este simulado ainda não está publicado.', 409);
        }
        if ((int) $simulado['quantidade_questoes'] < 1) {
            throw new SimuladoApiException('Este simulado não possui questões.', 409);
        }
        return $this->modelo->iniciarTentativa($simuladoId, $usuarioId);
    }

    public function questoesDaTentativa(int $tentativaId, int $usuarioId, bool $admin): array
    {
        $tentativa = $this->obterTentativa($tentativaId);
        $this->exigirAcessoTentativa($tentativa, $usuarioId, $admin, false);
        if ($tentativa['status'] !== 'em_andamento') {
            throw new SimuladoApiException('Esta tentativa já foi finalizada.', 409);
        }
        if ($this->modelo->tentativaExpirada($tentativaId)) {
            $this->modelo->finalizarTentativa($tentativaId);
            throw new SimuladoApiException('O tempo acabou; a tentativa foi finalizada automaticamente.', 409);
        }
        return [
            'tentativa' => [
                'id' => $tentativa['id'],
                'id_simulado' => $tentativa['id_simulado'],
                'iniciada_em' => $tentativa['iniciada_em'],
                'duracao_minutos' => $tentativa['duracao_minutos'] === null ? null : (int) $tentativa['duracao_minutos'],
            ],
            'questoes' => $this->modelo->obterQuestoesTentativa($tentativaId),
        ];
    }

    public function responder(array $dados, int $usuarioId, bool $admin): int
    {
        $tentativaId = idValido($dados['id_tentativa'] ?? null);
        $questaoId = idValido($dados['id_questao'] ?? null);
        if ($tentativaId === null || $questaoId === null) {
            throw new SimuladoApiException('id_tentativa e id_questao são obrigatórios.');
        }

        $tentativa = $this->obterTentativa($tentativaId);
        $this->exigirAcessoTentativa($tentativa, $usuarioId, $admin, false);
        if ($tentativa['status'] !== 'em_andamento') {
            throw new SimuladoApiException('Esta tentativa já foi finalizada.', 409);
        }
        if ($this->modelo->tentativaExpirada($tentativaId)) {
            $this->modelo->finalizarTentativa($tentativaId);
            throw new SimuladoApiException('O tempo acabou; a tentativa foi finalizada automaticamente.', 409);
        }
        if (!$this->modelo->questaoPertenceAoSimulado($questaoId, (int) $tentativa['id_simulado'])) {
            throw new SimuladoApiException('A questão não pertence ao simulado desta tentativa.', 422);
        }
        $tipo = $this->modelo->tipoDaQuestao($questaoId) ?? 'objetiva';

        $alternativaId = null;
        $respostaTexto = null;
        $justificativa = null;
        $correta = false;
        $statusCorrecao = 'automatica';

        if (in_array($tipo, Simulado::TIPOS_COM_ALTERNATIVAS, true)) {
            if (!array_key_exists('id_alternativa', $dados)) {
                throw new SimuladoApiException('Informe id_alternativa; use null para deixar a questão sem resposta.');
            }
            $bruta = $dados['id_alternativa'];
            if ($bruta !== null && $bruta !== '') {
                $alternativaId = idValido($bruta);
                if ($alternativaId === null) throw new SimuladoApiException('id_alternativa inválido.');
                $valor = $this->modelo->alternativaCorreta($questaoId, $alternativaId);
                if ($valor === null) {
                    throw new SimuladoApiException('A alternativa selecionada não pertence à questão.', 422);
                }
                // A correção objetiva é sempre calculada no servidor; o cliente nunca envia o campo correta.
                $correta = $valor;
            }
        }

        if ($tipo === 'discursiva') {
            if (!array_key_exists('resposta_texto', $dados)) {
                throw new SimuladoApiException('Informe resposta_texto; use null para deixar a questão sem resposta.');
            }
            $respostaTexto = $this->textoResposta($dados['resposta_texto'], 'resposta_texto');
        }

        if ($tipo === 'objetiva_justificativa') {
            if (!array_key_exists('justificativa', $dados)) {
                throw new SimuladoApiException('Informe justificativa; use null para deixá-la em branco.');
            }
            $justificativa = $this->textoResposta($dados['justificativa'], 'justificativa');
        }

        // Questões discursivas e com justificativa aguardam a correção de um administrador.
        if (in_array($tipo, Simulado::TIPOS_CORRECAO_MANUAL, true)) {
            $statusCorrecao = 'pendente';
        }
        return $this->modelo->salvarResposta($tentativaId, $questaoId, $alternativaId, $correta, $respostaTexto, $justificativa, $statusCorrecao);
    }

    /** Normaliza o texto livre enviado pelo aluno (null/vazio vira null). */
    private function textoResposta(mixed $valor, string $campo): ?string
    {
        if ($valor === null) return null;
        if (!is_string($valor)) throw new SimuladoApiException("{$campo} deve ser texto ou null.");
        $valor = trim($valor);
        if ($valor === '') return null;
        if (mb_strlen($valor) > 20000) {
            throw new SimuladoApiException("{$campo} excede 20.000 caracteres.", 422);
        }
        return $valor;
    }

    public function finalizar(int $tentativaId, int $usuarioId, bool $admin): array
    {
        $tentativa = $this->obterTentativa($tentativaId);
        $this->exigirAcessoTentativa($tentativa, $usuarioId, $admin, false);
        $tempoEsgotado = $tentativa['status'] === 'em_andamento'
            && $this->modelo->tentativaExpirada($tentativaId);
        $resultado = $this->modelo->finalizarTentativa($tentativaId);
        $resultado['tempo_esgotado'] = $tempoEsgotado;
        return $resultado;
    }

    public function resultado(int $tentativaId, int $usuarioId, bool $admin): array
    {
        $tentativa = $this->obterTentativa($tentativaId);
        $this->exigirAcessoTentativa($tentativa, $usuarioId, $admin);
        if ($tentativa['status'] === 'em_andamento') {
            return [
                'tentativa' => [
                    'id' => $tentativa['id'],
                    'id_simulado' => $tentativa['id_simulado'],
                    'status' => $tentativa['status'],
                    'iniciada_em' => $tentativa['iniciada_em'],
                ],
                'questoes' => [],
                'gabarito_disponivel' => false,
            ];
        }
        $resultado = $this->modelo->resultadoDetalhado($tentativaId);
        $resultado['gabarito_disponivel'] = true;
        return $resultado;
    }

    public function historico(int $usuarioId): array
    {
        return $this->modelo->historico($usuarioId);
    }

    // ------------------------------------------------------------------
    // Correção manual (somente administradores)
    // ------------------------------------------------------------------

    public function listarCorrecoes(bool $admin, mixed $statusInformado = null): array
    {
        $this->exigirAdmin($admin);
        $status = $statusInformado === null || $statusInformado === '' ? 'aguardando_correcao' : $statusInformado;
        if (!in_array($status, ['aguardando_correcao', 'concluida'], true)) {
            throw new SimuladoApiException('status deve ser aguardando_correcao ou concluida.');
        }
        return $this->modelo->listarCorrecoes($status);
    }

    public function detalheCorrecao(int $tentativaId, bool $admin): array
    {
        $this->exigirAdmin($admin);
        $tentativa = $this->obterTentativa($tentativaId);
        if ($tentativa['status'] === 'em_andamento') {
            throw new SimuladoApiException('Esta tentativa ainda está em andamento.', 409);
        }
        $resultado = $this->modelo->resultadoDetalhado($tentativaId);
        // Para o corretor interessam apenas as questões que exigem correção manual.
        $questoes = array_values(array_filter(
            $resultado['questoes'] ?? [],
            static fn(array $q): bool => in_array($q['tipo'], Simulado::TIPOS_CORRECAO_MANUAL, true)
        ));
        return [
            'tentativa' => $resultado['tentativa'] ?? $tentativa,
            'aluno' => $this->modelo->buscarAluno((int) $tentativa['id_usuario']),
            'questoes' => $questoes,
        ];
    }

    public function corrigir(array $dados, int $adminId, bool $admin): array
    {
        $this->exigirAdmin($admin);
        $tentativaId = idValido($dados['id_tentativa'] ?? null);
        $questaoId = idValido($dados['id_questao'] ?? null);
        if ($tentativaId === null || $questaoId === null) {
            throw new SimuladoApiException('id_tentativa e id_questao são obrigatórios.');
        }
        $tentativa = $this->obterTentativa($tentativaId);
        if ($tentativa['status'] === 'em_andamento') {
            throw new SimuladoApiException('A tentativa ainda está em andamento e não pode ser corrigida.', 409);
        }

        $resposta = $this->modelo->buscarRespostaParaCorrecao($tentativaId, $questaoId);
        if ($resposta === null) throw new SimuladoApiException('Questão não encontrada nesta tentativa.', 404);
        if (!in_array($resposta['tipo'], Simulado::TIPOS_CORRECAO_MANUAL, true)) {
            throw new SimuladoApiException('Questões objetivas são corrigidas automaticamente.', 422);
        }
        if (!in_array($resposta['status_correcao'], ['pendente', 'corrigida'], true)) {
            throw new SimuladoApiException('Esta questão foi deixada em branco e recebeu nota zero automaticamente.', 409);
        }

        if (!array_key_exists('pontos_obtidos', $dados) || !is_numeric($dados['pontos_obtidos'])) {
            throw new SimuladoApiException('Informe pontos_obtidos como número.');
        }
        $pontos = round((float) $dados['pontos_obtidos'], 2);
        if (!is_finite($pontos) || $pontos < 0 || $pontos > $resposta['pontos']) {
            throw new SimuladoApiException('A pontuação deve estar entre 0 e ' . rtrim(rtrim(number_format($resposta['pontos'], 2, '.', ''), '0'), '.') . '.', 422);
        }

        $comentario = $dados['comentario'] ?? null;
        if ($comentario !== null && !is_string($comentario)) {
            throw new SimuladoApiException('comentario deve ser texto ou null.');
        }
        $comentario = is_string($comentario) ? trim($comentario) : null;
        if ($comentario === '') $comentario = null;
        if ($comentario !== null && mb_strlen($comentario) > 5000) {
            throw new SimuladoApiException('O comentário excede 5.000 caracteres.', 422);
        }

        $resultado = $this->modelo->registrarCorrecao($tentativaId, $questaoId, $pontos, $comentario, $adminId);
        if ($resultado === null) {
            throw new SimuladoApiException('A tentativa ainda está em andamento e não pode ser corrigida.', 409);
        }
        return $resultado;
    }

    // ------------------------------------------------------------------
    // Ranking
    // ------------------------------------------------------------------

    public function ranking(int $usuarioId, bool $admin, mixed $materiaInformada = null): array
    {
        $materiaId = null;
        if ($materiaInformada !== null && $materiaInformada !== '') {
            $materiaId = idValido($materiaInformada);
            if ($materiaId === null) throw new SimuladoApiException('id_materia inválido.');
        }
        $lista = $this->modelo->ranking($materiaId);
        $minha = null;
        if (!$admin) {
            foreach ($lista as $linha) {
                if ($linha['id_usuario'] === $usuarioId) {
                    $minha = $linha;
                    break;
                }
            }
        }
        return [
            'ranking' => array_slice($lista, 0, 100),
            'total_participantes' => count($lista),
            'minha_posicao' => $minha,
            // Administradores não participam do ranking.
            'usuario_participa' => !$admin,
        ];
    }

    /**
     * Dados gerais de um simulado importado: as mesmas regras do cadastro manual
     * (matéria existente, título, descrição, duração), sem data importante associada.
     */
    public function normalizarDadosGeraisImportados(array $dados): array
    {
        $dados['id_data_importante'] = null;
        return $this->validarDadosGerais($dados);
    }

    /**
     * Questão de um rascunho importado, validada pelas mesmas regras de POST /api/simulados/questoes
     * (tipo, pontos, 2 a 10 alternativas, exatamente uma correta). A ordem é definida pela posição.
     *
     * @return array{enunciado: string, tipo: string, ordem: int, pontos: float, explicacao: ?string, alternativas: array}
     */
    public function normalizarQuestaoImportada(array $dados, int $ordem): array
    {
        $dados['ordem'] = $ordem;
        [$enunciado, $tipo, $ordemFinal, $pontos, $alternativas] = $this->validarQuestao($dados, 0, null);

        $explicacao = $dados['explicacao'] ?? null;
        if ($explicacao !== null && !is_string($explicacao)) {
            throw new SimuladoApiException('A explicação deve ser texto.', 422);
        }
        $explicacao = is_string($explicacao) ? trim($explicacao) : null;
        if ($explicacao === '') {
            $explicacao = null;
        }
        if ($explicacao !== null && mb_strlen($explicacao, 'UTF-8') > 20000) {
            throw new SimuladoApiException('A explicação excede 20.000 caracteres.', 422);
        }

        return [
            'enunciado' => $enunciado,
            'tipo' => $tipo,
            'ordem' => $ordemFinal,
            'pontos' => round($pontos, 2),
            'explicacao' => $explicacao,
            'alternativas' => $alternativas,
        ];
    }

    private function validarDadosGerais(array $dados): array
    {
        $materiaId = idValido($dados['id_materia'] ?? null);
        if ($materiaId === null || !$this->modelo->existeMateria($materiaId)) {
            throw new SimuladoApiException('id_materia inválido ou matéria inexistente.', 422);
        }

        $dataId = null;
        if (array_key_exists('id_data_importante', $dados) && $dados['id_data_importante'] !== null && $dados['id_data_importante'] !== '') {
            $dataId = idValido($dados['id_data_importante']);
            if ($dataId === null || !$this->modelo->existeData($dataId)) {
                throw new SimuladoApiException('A data importante selecionada não existe.', 422);
            }
            $materiaDaData = $this->modelo->materiaDaData($dataId);
            if ($materiaDaData !== null && $materiaDaData !== $materiaId) {
                throw new SimuladoApiException('A data importante pertence a outra matéria.', 422);
            }
        }

        $titulo = is_string($dados['titulo'] ?? null) ? trim($dados['titulo']) : '';
        if ($titulo === '' || mb_strlen($titulo) > 150) {
            throw new SimuladoApiException('Título obrigatório, com no máximo 150 caracteres.');
        }
        $descricao = $dados['descricao'] ?? null;
        if ($descricao !== null && !is_string($descricao)) {
            throw new SimuladoApiException('descricao deve ser texto ou null.');
        }
        $descricao = is_string($descricao) ? trim($descricao) : null;
        if ($descricao === '') $descricao = null;
        if ($descricao !== null && mb_strlen($descricao) > 10000) {
            throw new SimuladoApiException('A descrição excede 10.000 caracteres.');
        }

        $duracao = null;
        if (array_key_exists('duracao_minutos', $dados) && $dados['duracao_minutos'] !== null && $dados['duracao_minutos'] !== '') {
            $bruto = $dados['duracao_minutos'];
            if (!(is_int($bruto) || (is_string($bruto) && ctype_digit($bruto)))) {
                throw new SimuladoApiException('duracao_minutos deve ser um número inteiro.');
            }
            $duracao = (int) $bruto;
            if ($duracao < 1 || $duracao > 600) {
                throw new SimuladoApiException('A duração deve ser entre 1 e 600 minutos.');
            }
        }

        return [$materiaId, $dataId, $titulo, $descricao, $duracao];
    }

    private function validarQuestao(array $dados, int $simuladoId, ?int $questaoId): array
    {
        $enunciado = is_string($dados['enunciado'] ?? null) ? trim($dados['enunciado']) : '';
        if ($enunciado === '' || mb_strlen($enunciado) > 20000) {
            throw new SimuladoApiException('Enunciado obrigatório, com no máximo 20.000 caracteres.');
        }

        // Sem "tipo" informado, a questão é objetiva (compatível com clientes anteriores).
        $tipo = $dados['tipo'] ?? 'objetiva';
        if (!is_string($tipo) || !in_array($tipo, Simulado::TIPOS, true)) {
            throw new SimuladoApiException('tipo deve ser objetiva, discursiva ou objetiva_justificativa.', 422);
        }

        if (isset($dados['ordem']) && $dados['ordem'] !== '') {
            $ordem = idValido($dados['ordem']);
            if ($ordem === null || $ordem > 10000) throw new SimuladoApiException('ordem deve ser um inteiro positivo.');
        } elseif ($questaoId !== null) {
            $questaoExistente = $this->modelo->buscarQuestao($questaoId);
            if ($questaoExistente === null || (int) $questaoExistente['id_simulado'] !== $simuladoId) {
                throw new SimuladoApiException('Questão não encontrada neste simulado.', 404);
            }
            $ordem = (int) $questaoExistente['ordem'];
        } else {
            $ordem = $this->modelo->proximaOrdem($simuladoId);
        }

        $pontosBrutos = $dados['pontos'] ?? 1;
        if (!is_numeric($pontosBrutos)) throw new SimuladoApiException('pontos deve ser numérico.');
        $pontos = (float) $pontosBrutos;
        if (!is_finite($pontos) || $pontos <= 0 || $pontos > 100) {
            throw new SimuladoApiException('A pontuação deve ser maior que zero e no máximo 100.');
        }

        $normalizadas = [];
        if (in_array($tipo, Simulado::TIPOS_COM_ALTERNATIVAS, true)) {
            $alternativas = $dados['alternativas'] ?? null;
            if (!is_array($alternativas) || count($alternativas) < 2 || count($alternativas) > 10) {
                throw new SimuladoApiException('Cada questão objetiva deve conter entre 2 e 10 alternativas.');
            }
            $quantasCorretas = 0;
            foreach ($alternativas as $alternativa) {
                if (!is_array($alternativa)) throw new SimuladoApiException('Formato de alternativa inválido.');
                $texto = is_string($alternativa['texto'] ?? null) ? trim($alternativa['texto']) : '';
                if ($texto === '' || mb_strlen($texto) > 4000) {
                    throw new SimuladoApiException('O texto de cada alternativa é obrigatório e limitado a 4.000 caracteres.');
                }
                if (!is_bool($alternativa['correta'] ?? null)) {
                    throw new SimuladoApiException('Cada alternativa deve informar correta como true ou false.');
                }
                if ($alternativa['correta']) $quantasCorretas++;
                $normalizadas[] = ['texto' => $texto, 'correta' => $alternativa['correta']];
            }
            if ($quantasCorretas !== 1) {
                throw new SimuladoApiException('Cada questão objetiva deve ter exatamente uma alternativa correta.');
            }
        } elseif (isset($dados['alternativas']) && $dados['alternativas'] !== [] && $dados['alternativas'] !== null) {
            throw new SimuladoApiException('Questões discursivas não possuem alternativas.', 422);
        }

        if ($questaoId !== null) {
            $stmt = $this->modelo->buscarQuestao($questaoId);
            if ($stmt === null || (int) $stmt['id_simulado'] !== $simuladoId) {
                throw new SimuladoApiException('Questão não encontrada neste simulado.', 404);
            }
        }

        return [$enunciado, $tipo, $ordem, $pontos, $normalizadas];
    }

    private function obterSimulado(int $id): array
    {
        $simulado = $this->modelo->buscarPorId($id);
        if ($simulado === null) throw new SimuladoApiException('Simulado não encontrado.', 404);
        return $simulado;
    }

    private function obterTentativa(int $id): array
    {
        $tentativa = $this->modelo->buscarTentativa($id);
        if ($tentativa === null) throw new SimuladoApiException('Tentativa não encontrada.', 404);
        return $tentativa;
    }

    /** Somente administradores criam e gerenciam simulados (inclusive os rascunhos antigos de outros usuários). */
    private function podeGerenciar(array $simulado, int $usuarioId, bool $admin): bool
    {
        return $admin;
    }

    private function exigirAdmin(bool $admin): void
    {
        if (!$admin) {
            throw new SimuladoApiException('Apenas administradores podem realizar esta ação.', 403);
        }
    }

    private function exigirGerencia(array $simulado, int $usuarioId, bool $admin): void
    {
        if (!$this->podeGerenciar($simulado, $usuarioId, $admin)) {
            throw new SimuladoApiException('Você não tem permissão para administrar este simulado.', 403);
        }
    }

    private function exigirRascunhoSemTentativas(array $simulado): void
    {
        if ($simulado['status'] !== 'rascunho') {
            throw new SimuladoApiException('Um simulado publicado não pode ser alterado; o histórico precisa permanecer consistente.', 409);
        }
        if ($this->modelo->possuiTentativas((int) $simulado['id'])) {
            throw new SimuladoApiException('Não é possível alterar um simulado que já possui tentativas.', 409);
        }
    }

    private function exigirAcessoTentativa(array $tentativa, int $usuarioId, bool $admin, bool $adminPodeVer = true): void
    {
        if ((int) $tentativa['id_usuario'] !== $usuarioId && !($admin && $adminPodeVer)) {
            throw new SimuladoApiException('Tentativa não encontrada.', 404);
        }
    }
}
