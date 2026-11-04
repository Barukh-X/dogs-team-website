<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/auth_admin_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../services/SimuladoService.php';
require_once __DIR__ . '/../services/SimuladoImportacaoService.php';

class SimuladoController
{
    private SimuladoService $service;
    private PDO $pdo;

    public function __construct()
    {
        exigirAutenticacao();
        $this->pdo = ConectarDB();
        $this->service = new SimuladoService($this->pdo);
    }

    private function usuarioId(): int
    {
        return (int) $GLOBALS['auth_user']['id'];
    }

    private function ehAdmin(): bool
    {
        return !empty($GLOBALS['auth_user']['isadmin']);
    }

    private function capturarErro(Throwable $e): void
    {
        if ($e instanceof SimuladoImportacaoInvalidaException) {
            responderJson($e->httpStatus, ['sucesso' => false, 'erro' => $e->getMessage(), 'detalhes' => $e->detalhes]);
            return;
        }
        if ($e instanceof SimuladoApiException) {
            responderJson($e->httpStatus, ['sucesso' => false, 'erro' => $e->getMessage()]);
            return;
        }
        if ($e instanceof OutOfBoundsException) {
            responderJson(404, ['sucesso' => false, 'erro' => $e->getMessage()]);
            return;
        }
        if ($e instanceof PDOException && $e->getCode() === '23000') {
            responderJson(409, ['sucesso' => false, 'erro' => 'A operação viola uma restrição do banco de dados.']);
            return;
        }

        error_log('Dogs Team - erro no módulo de simulados: ' . $e->getMessage());
        responderJson(500, ['sucesso' => false, 'erro' => 'Erro interno ao processar o simulado.']);
    }

    private function dadosObrigatorios(): ?array
    {
        $dados = lerJson();
        if ($dados === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'JSON inválido.']);
            return null;
        }
        return $dados;
    }

    public function listar(): void
    {
        try {
            responderJson(200, [
                'sucesso' => true,
                'simulados' => $this->service->listar($this->usuarioId(), $this->ehAdmin(), $_GET['id_materia'] ?? null),
            ]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function detalhes(): void
    {
        $id = idValido($_GET['id'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'ID do simulado inválido.']);
            return;
        }
        try {
            responderJson(200, ['sucesso' => true, 'simulado' => $this->service->detalhes($id, $this->usuarioId(), $this->ehAdmin())]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function criar(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $id = $this->service->criar($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(201, ['sucesso' => true, 'mensagem' => 'Simulado criado como rascunho.', 'id' => $id]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function editar(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $this->service->editar($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Simulado atualizado.']);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function excluir(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        $id = idValido($dados['id'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'ID do simulado inválido.']);
            return;
        }
        try {
            $this->service->excluir($id, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Simulado excluído.']);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function publicar(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        $id = idValido($dados['id'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'ID do simulado inválido.']);
            return;
        }
        try {
            $this->service->publicar($id, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Simulado publicado.']);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function adicionarQuestao(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $id = $this->service->adicionarQuestao($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(201, ['sucesso' => true, 'mensagem' => 'Questão adicionada.', 'id' => $id]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function editarQuestao(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $this->service->editarQuestao($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Questão atualizada.']);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function excluirQuestao(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        $id = idValido($dados['id'] ?? $dados['id_questao'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'ID da questão inválido.']);
            return;
        }
        try {
            $this->service->excluirQuestao($id, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Questão excluída.']);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function iniciar(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        $idSimulado = idValido($dados['id_simulado'] ?? null);
        if ($idSimulado === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_simulado obrigatório e inválido.']);
            return;
        }
        try {
            $id = $this->service->iniciar($idSimulado, $this->usuarioId());
            responderJson(201, ['sucesso' => true, 'id_tentativa' => $id, 'mensagem' => 'Tentativa iniciada.']);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function questoesTentativa(): void
    {
        $id = idValido($_GET['id_tentativa'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_tentativa obrigatório e inválido.']);
            return;
        }
        try {
            responderJson(200, ['sucesso' => true] + $this->service->questoesDaTentativa($id, $this->usuarioId(), $this->ehAdmin()));
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function responder(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $idResposta = $this->service->responder($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Resposta salva.', 'id_resposta' => $idResposta]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function finalizar(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        $id = idValido($dados['id_tentativa'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_tentativa obrigatório e inválido.']);
            return;
        }
        try {
            responderJson(200, ['sucesso' => true, 'resultado' => $this->service->finalizar($id, $this->usuarioId(), $this->ehAdmin())]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function resultado(): void
    {
        $id = idValido($_GET['id_tentativa'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_tentativa obrigatório e inválido.']);
            return;
        }
        try {
            responderJson(200, ['sucesso' => true] + $this->service->resultado($id, $this->usuarioId(), $this->ehAdmin()));
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    public function historico(): void
    {
        try {
            responderJson(200, ['sucesso' => true, 'historico' => $this->service->historico($this->usuarioId())]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    /** Administrador: lista tentativas com questões para corrigir (?status=aguardando_correcao|concluida). */
    public function listarCorrecoes(): void
    {
        try {
            responderJson(200, [
                'sucesso' => true,
                'correcoes' => $this->service->listarCorrecoes($this->ehAdmin(), $_GET['status'] ?? null),
            ]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    /** Administrador: respostas de uma tentativa que dependem de correção manual. */
    public function detalheCorrecao(): void
    {
        $id = idValido($_GET['id_tentativa'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_tentativa obrigatório e inválido.']);
            return;
        }
        try {
            responderJson(200, ['sucesso' => true] + $this->service->detalheCorrecao($id, $this->ehAdmin()));
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    /** Administrador: registra a nota (e um comentário opcional) de uma resposta discursiva/justificada. */
    public function corrigir(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $resultado = $this->service->corrigir($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Correção registrada.', 'resultado' => $resultado]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    /** Ranking de pontuação dos estudantes (administradores não participam). Aceita ?id_materia=ID. */
    public function ranking(): void
    {
        try {
            responderJson(200, ['sucesso' => true] + $this->service->ranking($this->usuarioId(), $this->ehAdmin(), $_GET['id_materia'] ?? null));
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    /**
     * Administrador: recebe um arquivo .md/.txt (multipart, campo "arquivo"), valida e devolve o rascunho
     * com erros e avisos por questão. NADA é gravado no banco nem em disco.
     */
    public function importar(): void
    {
        try {
            $servico = new SimuladoImportacaoService($this->pdo);
            responderJson(200, ['sucesso' => true] + $servico->analisar($_FILES, $this->ehAdmin()));
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }

    /** Administrador: grava o rascunho revisado como simulado em rascunho (transação única). */
    public function confirmarImportacao(): void
    {
        $dados = $this->dadosObrigatorios();
        if ($dados === null) return;
        try {
            $servico = new SimuladoImportacaoService($this->pdo);
            $resultado = $servico->confirmar($dados, $this->usuarioId(), $this->ehAdmin());
            responderJson(201, [
                'sucesso' => true,
                'mensagem' => 'Simulado importado como rascunho.',
                'id' => $resultado['id'],
                'total_questoes' => $resultado['total_questoes'],
            ]);
        } catch (Throwable $e) {
            $this->capturarErro($e);
        }
    }
}
