<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Anotacao.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/auth_admin_check.php';

class AnotacaoController
{
    private const MAX_LINKS = 20;

    private Anotacao $anotacaoModel;

    public function __construct()
    {
        exigirAutenticacao();
        $this->anotacaoModel = new Anotacao(ConectarDB());
    }

    private function usuarioId(): int
    {
        return (int) $GLOBALS['auth_user']['id'];
    }

    private function ehAdmin(): bool
    {
        return !empty($GLOBALS['auth_user']['isadmin']);
    }

    // Retorna lista de links normalizada, ou null (já respondendo 400) se algum for inválido
    private function validarLinks(mixed $links): ?array
    {
        if (!is_array($links) || count($links) > self::MAX_LINKS) {
            responderJson(400, ['erro' => 'links deve ser uma lista (máx. ' . self::MAX_LINKS . ' itens)']);
            return null;
        }

        $normalizados = [];

        foreach ($links as $link) {
            $url = is_array($link) && is_string($link['url'] ?? null) ? trim($link['url']) : '';
            $titulo = is_array($link) && is_string($link['titulo'] ?? null) ? trim($link['titulo']) : '';
            $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            if (
                $url === '' || mb_strlen($url) > 500
                || !filter_var($url, FILTER_VALIDATE_URL)
                || !in_array($esquema, ['http', 'https'], true)
            ) {
                responderJson(400, ['erro' => 'Link inválido (use http/https, máx. 500 caracteres)']);
                return null;
            }

            if (mb_strlen($titulo) > 100) {
                responderJson(400, ['erro' => 'Título do link muito longo (máx. 100 caracteres)']);
                return null;
            }

            $normalizados[] = ['url' => $url, 'titulo' => $titulo === '' ? null : $titulo];
        }

        return $normalizados;
    }

    // Retorna [materiaId, titulo, descricao, publica, links], ou null (já respondendo o erro)
    private function validarCampos(array $d): ?array
    {
        $materiaId = idValido($d['id_materia'] ?? null);
        if ($materiaId === null) {
            responderJson(400, ['erro' => 'id_materia obrigatório']);
            return null;
        }

        $titulo = is_string($d['titulo'] ?? null) ? trim($d['titulo']) : '';
        if ($titulo === '' || mb_strlen($titulo) > 100) {
            responderJson(400, ['erro' => 'Título obrigatório (máx. 100 caracteres)']);
            return null;
        }

        $descricao = is_string($d['descricao_anot'] ?? null) ? trim($d['descricao_anot']) : '';

        // Admin: pública por padrão (pode mandar publica=false). Usuário comum: sempre privada.
        $publica = $this->ehAdmin();
        if (array_key_exists('publica', $d)) {
            if (!is_bool($d['publica'])) {
                responderJson(400, ['erro' => 'publica deve ser true ou false']);
                return null;
            }
            if ($d['publica'] && !$this->ehAdmin()) {
                responderJson(403, ['erro' => 'Apenas administradores podem criar anotações públicas']);
                return null;
            }
            $publica = $d['publica'];
        }

        $links = null;
        if (array_key_exists('links', $d)) {
            $links = $this->validarLinks($d['links']);
            if ($links === null) return null;
        }

        return [$materiaId, $titulo, $descricao, $publica, $links];
    }

    public function listar(): void
    {
        $materiaId = null;

        if (isset($_GET['id_materia'])) {
            $materiaId = idValido($_GET['id_materia']);
            if ($materiaId === null) {
                responderJson(400, ['erro' => 'id_materia inválido']);
                return;
            }
        }

        $anotacoes = $this->anotacaoModel->listar_anotacoes($this->usuarioId(), $materiaId);

        responderJson(200, ['anotacoes' => $anotacoes]);
    }

    public function criar(): void
    {
        $dados = lerJson();

        if ($dados === null) {
            responderJson(400, ['erro' => 'JSON inválido']);
            return;
        }

        $campos = $this->validarCampos($dados);
        if ($campos === null) return;

        [$materiaId, $titulo, $descricao, $publica, $links] = $campos;

        try {
            $id = $this->anotacaoModel->criar(
                $this->usuarioId(),
                $materiaId,
                $titulo,
                $descricao,
                $publica,
                $links ?? []
            );

            responderJson(201, ['sucesso' => true, 'mensagem' => 'Anotação criada com sucesso', 'id' => $id]);
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
        $dados = lerJson();
        $id = $dados !== null ? idValido($dados['id'] ?? null) : null;

        if ($id === null) {
            responderJson(400, ['erro' => 'ID da anotação inválido']);
            return;
        }

        $campos = $this->validarCampos($dados);
        if ($campos === null) return;

        [$materiaId, $titulo, $descricao, , $links] = $campos;

        try {
            $editado = $this->anotacaoModel->editar(
                $id,
                $this->usuarioId(),
                $this->ehAdmin(),
                $materiaId,
                $titulo,
                $descricao,
                $links
            );

            if ($editado) {
                responderJson(200, ['sucesso' => true, 'mensagem' => 'Anotação editada com sucesso']);
            } else {
                responderJson(404, ['sucesso' => false, 'erro' => 'Anotação não encontrada']);
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
        $dados = lerJson();
        $id = $dados !== null ? idValido($dados['id'] ?? null) : null;

        if ($id === null) {
            responderJson(400, ['erro' => 'ID da anotação inválido']);
            return;
        }

        try {
            if ($this->anotacaoModel->excluir($id, $this->usuarioId(), $this->ehAdmin())) {
                responderJson(200, ['sucesso' => true, 'mensagem' => 'Anotação excluída com sucesso']);
            } else {
                responderJson(404, ['sucesso' => false, 'erro' => 'Anotação não encontrada']);
            }
        } catch (PDOException $e) {
            responderJson(500, ['erro' => 'Erro interno do sistema']);
        }
    }
}