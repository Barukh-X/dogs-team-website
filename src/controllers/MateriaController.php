<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Materia.php';
require_once __DIR__ . '/../models/DataImportante.php';
require_once __DIR__ . '/../models/Anotacao.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/auth_admin_check.php';
require_once __DIR__ . '/../includes/helpers.php';

class MateriaController
{
    private Materia $materiaModel;
    private DataImportante $dataImportanteModel;
    private Anotacao $anotacaoModel;

    public function __construct()
    {
        exigirAutenticacao();
        $pdo = ConectarDB();

        $this->materiaModel = new Materia($pdo);
        $this->dataImportanteModel = new DataImportante($pdo);
        $this->anotacaoModel = new Anotacao($pdo);
    }

    private function validarNome(array $dados): ?string
    {
        $nome = is_string($dados['nome_materia'] ?? null) ? trim($dados['nome_materia']) : '';

        if ($nome === '' || mb_strlen($nome) > 30) {
            responderJson(400, ['erro' => 'Nome da matéria obrigatório (máx. 30 caracteres)']);
            return null;
        }

        return $nome;
    }

    public function listar(): void
    {
        $materias = $this->materiaModel->listar_materias();

        responderJson(200, ['materias' => $materias]);
    }

    public function detalhes(): void
    {
        $id = idValido($_GET['id'] ?? null);

        if ($id === null) {
            responderJson(400, ['erro' => 'ID da matéria inválido']);
            return;
        }

        $materia = $this->materiaModel->buscarPorId($id);

        if ($materia === null) {
            responderJson(404, ['erro' => 'Matéria não encontrada']);
            return;
        }

        $usuarioId = (int) $GLOBALS['auth_user']['id'];

        responderJson(200, [
            'materia'   => $materia,
            'datas'     => $this->dataImportanteModel->listarPorMateria($id),
            'anotacoes' => $this->anotacaoModel->listar_anotacoes($usuarioId, $id),
        ]);
    }

    public function criar(): void
    {
        exigirAdmin();

        $dados = lerJson();

        if ($dados === null) {
            responderJson(400, ['erro' => 'JSON inválido']);
            return;
        }

        $nome_materia = $this->validarNome($dados);
        if ($nome_materia === null) return;

        try {
            $criado = $this->materiaModel->criar($nome_materia);

            if ($criado) {
                responderJson(201, ['sucesso' => true, 'mensagem' => 'Matéria criada com sucesso']);
            } else {
                responderJson(500, ['erro' => 'Erro ao criar matéria']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                responderJson(409, ['erro' => 'Matéria já cadastrada']);
                return;
            }
            responderJson(500, ['erro' => 'Erro interno do sistema']);
        }
    }

    public function editar(): void
    {
        exigirAdmin();

        $dados = lerJson();
        $id = $dados !== null ? idValido($dados['id'] ?? null) : null;

        if ($id === null) {
            responderJson(400, ['erro' => 'ID da matéria inválido']);
            return;
        }

        $nome_materia = $this->validarNome($dados);
        if ($nome_materia === null) return;

        try {
            $editado = $this->materiaModel->editar($id, $nome_materia);

            if ($editado) {
                responderJson(200, ['sucesso' => true, 'mensagem' => 'Nome da matéria editado com sucesso']);
            } else {
                responderJson(404, ['sucesso' => false, 'erro' => 'Matéria não encontrada']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                responderJson(409, ['sucesso' => false, 'erro' => 'Matéria já cadastrada']);
                return;
            }
            responderJson(500, ['sucesso' => false, 'erro' => 'Erro interno do sistema']);
        }
    }

    public function excluir(): void
    {
        exigirAdmin();

        $dados = lerJson();
        $id = $dados !== null ? idValido($dados['id'] ?? null) : null;

        if ($id === null) {
            responderJson(400, ['erro' => 'ID da matéria inválido']);
            return;
        }

        try {
            $excluido = $this->materiaModel->excluir($id);

            if ($excluido) {
                responderJson(200, ['sucesso' => true, 'mensagem' => 'Matéria excluída com sucesso']);
            } else {
                responderJson(404, ['sucesso' => false, 'erro' => 'Matéria não encontrada']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                responderJson(409, ['sucesso' => false, 'erro' => 'Matéria possui datas importantes vinculadas']);
                return;
            }
            responderJson(500, ['erro' => 'Erro interno do sistema']);
        }
    }
}