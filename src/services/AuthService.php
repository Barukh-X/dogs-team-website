<?php

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/JwtService.php';

class AuthService
{
    private Usuario $usuarioModel;
    private JwtService $jwt;

    public function __construct(PDO $pdo)
    {
        $this->usuarioModel = new Usuario($pdo);
        $this->jwt = new JwtService();
    }

    public function autenticar(string $login, string $senha): ?string
    {
        $usuario = $this->usuarioModel->buscarPorLogin($login);

        if ($usuario === null || !password_verify($senha, $usuario['senha'])) {
            return null;
        }

        return $this->jwt->gerar([
            'sub' => $usuario['id'],
            'isadmin' => (bool) $usuario['isadmin'],
        ]);
    }

    public function registrar(string $nome, string $usuario, string $email, string $senha): bool
    {
        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        return $this->usuarioModel->criar($nome, $usuario, $email, $senhaHash);
    }
}