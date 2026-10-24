<?php

require_once __DIR__ . '/../services/JwtService.php';

function obterHeaderAutorizacao(): string
{
    if (function_exists('getallheaders')) {
        foreach (getallheaders() as $chave => $valor) {
            $chave = strtolower($chave);
            if ($chave === 'authorization' || $chave === 'x-authorization') {
                return $valor;
            }
        }
    }

    // Alguns servidores removem o cabeçalho Authorization; X-Authorization é o plano B.
    return $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? $_SERVER['HTTP_X_AUTHORIZATION']
        ?? '';
}

function exigirAutenticacao(): void
{
    $authHeader = obterHeaderAutorizacao();

    if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Token não enviado']);
        exit;
    }

    $payload = (new JwtService())->validar($matches[1]);

    if ($payload === null) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'erro' => 'Token inválido ou expirado']);
        exit;
    }

    $GLOBALS['auth_user'] = [
        'id' => $payload['sub'],
        'isadmin' => $payload['isadmin'] ?? false,
    ];
}