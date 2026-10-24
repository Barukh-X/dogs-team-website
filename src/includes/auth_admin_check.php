<?php

require_once __DIR__ . '/auth_check.php';

function exigirAdmin(): void
{
    exigirAutenticacao();

    if (empty($GLOBALS['auth_user']['isadmin'])) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['sucesso' => false, 'mensagem' => 'Usuário não autorizado']);
        exit;
    }
}