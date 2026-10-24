<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/DataImportante.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/auth_admin_check.php';
require_once __DIR__ . '/../includes/helpers.php';

class DataImportanteController
{
    private DataImportante $dataImportanteModel;
    
    private function normalizarDataHora(mixed $v): ?string
    {
        if (!is_string($v)) return null;

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $formato) {
            $dt = DateTime::createFromFormat($formato, $v);
            
            if ($dt && $dt->format($formato) === $v) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
        return null;
    }
    
    private function validarCampos(array $d): ?array
    {
        $titulo = isset($d['titulo']) && is_string($d['titulo']) ? trim($d['titulo']) : '';
        $dataHora = $this->normalizarDataHora($d['data_hora'] ?? null);
        $materiaId = null;

        if ($titulo === '' || mb_strlen($titulo) > 50) {
            responderJson(400, ['erro' => 'Título obrigatório (máx. 50 caracteres)']);
            return null;
        }
        if ($dataHora === null) {
            responderJson(400, ['erro' => 'data_hora inválida']);
            return null;
        }
        if (isset($d['id_materia']) && $d['id_materia'] !== '') {
            $materiaId = idValido($d['id_materia']);
            if ($materiaId === null) {
                responderJson(400, ['erro' => 'id_materia inválido']);
                return null;
            }
        }

        $desc = isset($d['descricao']) && is_string($d['descricao']) ? trim($d['descricao']) : '';

        return [$titulo, $desc, $dataHora, $materiaId];
    }

    public function __construct()
    {
        exigirAutenticacao();
        $this->dataImportanteModel = new DataImportante(ConectarDB());
    }

    public function listar(): void
    {
        $datas_importantes = $this->dataImportanteModel->listar();

        http_response_code(200);
        echo json_encode(['datas_importantes' => $datas_importantes]);
    }

    public function criar(): void
    {
        exigirAdmin();

        $dados = lerJson();
        if ($dados === null) {
            responderJson(400, ['erro' => 'JSON inválido']);
            return;
        }

        $campos = $this->validarCampos($dados);
        if ($campos === null) return;

        try {
            $this->dataImportanteModel->criar(...$campos);
            responderJson(201, ['sucesso' => true, 'mensagem' => 'Data importante criada com sucesso']);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                responderJson(422, ['erro' => 'Matéria inexistente']);
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
            responderJson(400, ['erro' => 'ID inválido']);
            return;
        }

        $campos = $this->validarCampos($dados);
        if ($campos === null) return;

        try {
            if ($this->dataImportanteModel->editar($id, ...$campos)) {
                responderJson(200, ['sucesso' => true, 'mensagem' => 'Data importante editada com sucesso']);
            } else {
                responderJson(404, ['sucesso' => false, 'erro' => 'Data importante não encontrada']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                responderJson(422, ['erro' => 'Matéria inexistente']);
                return;
            }
            responderJson(500, ['erro' => 'Erro interno do sistema']);
        }
    }

    public function excluir(): void
    {
        exigirAdmin();

        $dados = lerJson();
        $id = $dados !== null ? idValido($dados['id'] ?? null) : null;

        if ($id === null) {
            responderJson(400, ['erro' => 'ID inválido']);
            return;
        }

        try {
            if ($this->dataImportanteModel->excluir($id)) {
                responderJson(200, ['sucesso' => true, 'mensagem' => 'Data importante excluída com sucesso']);
            } else {
                responderJson(404, ['sucesso' => false, 'erro' => 'Data importante não encontrada']);
            }
        } catch (PDOException $e) {
            responderJson(500, ['erro' => 'Erro interno do sistema']);
        }
    }
}
