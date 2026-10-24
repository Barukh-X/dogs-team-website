<?php

function lerJson(): ?array
{
    $dados = json_decode(file_get_contents('php://input'), true);
    return is_array($dados) ? $dados : null;
}

function idValido(mixed $v): ?int
{
    if (is_int($v) || (is_string($v) && ctype_digit($v))) {
        return (int) $v > 0 ? (int) $v : null;
    }
    return null;
}

function responderJson(int $status, array $corpo): void
{
    http_response_code($status);
    echo json_encode($corpo);
}