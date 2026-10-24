<?php

class Usuario
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function buscarPorLogin(string $login): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, senha, isadmin FROM usuarios WHERE usuario = :logar_usuario OR email = :logar_email LIMIT 1');
        
        $stmt->execute(['logar_usuario' => $login, 'logar_email' => $login]);
        
        $res_busca = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $res_busca ?: null;
    }

    public function criar(string $nome, string $usuario, string $email, string $senha): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO usuarios (nome, usuario, email, senha) VALUES (:nome, :usuario, :email, :senha)");
        
        return $stmt->execute([
            'nome' => $nome,
            'usuario' => $usuario,
            'email' => $email,
            'senha' => $senha
        ]);
    }
}
