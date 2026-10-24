<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/AuthService.php';
require_once __DIR__ . '/../includes/helpers.php';

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService(ConectarDB());
    }

    public function login(): void
    {
        $dados = lerJson();

        if ($dados === null) {
            responderJson(400, ['erro' => 'JSON inválido']);
            return;
        }

        $logar = $dados['logar'] ?? null;
        $senha = $dados['senha'] ?? null;

        if (!is_string($logar) || !is_string($senha) || $logar === '' || $senha === '') {
            responderJson(400, ['erro' => 'Usuário e senha são obrigatórios']);
            return;
        }

        $token = $this->authService->autenticar($logar, $senha);

        if ($token === null) {
            responderJson(401, ['erro' => 'Usuário ou senha inválidos']);
            return;
        }

        responderJson(200, ['sucesso' => true, 'token' => $token]);
    }

    public function cadastrar(): void
    {
        $dados = lerJson();

        if ($dados === null) {
            responderJson(400, ['erro' => 'JSON inválido']);
            return;
        }

        $texto = fn(string $campo): string =>
            is_string($dados[$campo] ?? null) ? trim($dados[$campo]) : '';

        $nome    = $texto('nome');
        $usuario = $texto('usuario');
        $email   = $texto('email');
        $senha   = is_string($dados['senha'] ?? null) ? $dados['senha'] : '';

        if ($nome === '' || $usuario === '' || $email === '' || $senha === '') {
            responderJson(400, ['erro' => 'Preencha todos os campos corretamente']);
            return;
        }

        if (mb_strlen($nome) > 30 || mb_strlen($usuario) > 30 || mb_strlen($email) > 50) {
            responderJson(400, ['erro' => 'Nome, usuário ou email excedem o tamanho permitido']);
            return;
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            responderJson(400, ['erro' => 'Email inválido']);
            return;
        }

        try {
            $cadastro = $this->authService->registrar($nome, $usuario, $email, $senha);

            if ($cadastro) {
                responderJson(201, ['sucesso' => true]);
            } else {
                responderJson(500, ['erro' => 'Erro ao cadastrar usuário']);
            }
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') {
                responderJson(409, ['erro' => 'Nome de usuário ou email já cadastrados']);
                return;
            }
            responderJson(500, ['erro' => 'Erro interno do sistema']);
        }
    }

    public function logout(): void
    {
        responderJson(200, ['sucesso' => true]);
    }
}