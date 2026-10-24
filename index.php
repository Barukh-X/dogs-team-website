<?php


require_once __DIR__ . '/src/config/environment.php';

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$appDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');

// Remove o prefixo da pasta do projeto (por exemplo, /dogs-team-php no XAMPP).
if ($appDirectory !== '' && $appDirectory !== '/' && str_starts_with($requestPath, $appDirectory)) {
    $nextCharacter = substr($requestPath, strlen($appDirectory), 1);
    if ($nextCharacter === '' || $nextCharacter === '/') {
        $requestPath = substr($requestPath, strlen($appDirectory)) ?: '/';
    }
}

// O servidor embutido do PHP não lê .htaccess. Em ambiente de desenvolvimento,
// permite que ele sirva apenas os assets e as páginas públicas existentes.
if (PHP_SAPI === 'cli-server' && preg_match('~^/api(?:/|$)~', $requestPath) !== 1) {
    $arquivoPublico = ltrim($requestPath, '/');
    $publicoPermitido = preg_match(
        '~^(?:index|login|cadastro|dashboard|materias|anotacoes|calendario|recuperar-senha)\.php$|^(?:style\.css|api\.js|app\.js|script\.js|cadastro_script\.js|dashboard\.js|materias\.js|anotacoes\.js|calendario\.js)$|^imagens/[A-Za-z0-9_.-]+\.(?:png|jpe?g|webp)$~i',
        $arquivoPublico
    ) === 1;
    if ($publicoPermitido && is_file(__DIR__ . '/' . $arquivoPublico)) {
        return false;
    }
}

// Todas as chamadas /api/* passam pelo roteador da API.
if (preg_match('~^/api(?:/|$)~', $requestPath) === 1) {
    // Qualquer erro não tratado na API vira uma resposta JSON em vez de um 500 vazio.
    set_exception_handler(function (Throwable $e): void {
        error_log('Dogs Team - erro: ' . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        $resposta = ['sucesso' => false, 'erro' => 'Erro interno do servidor'];
        if (dogsTeamConfig('DB_DEBUG', '0') === '1') {
            $resposta['detalhe'] = $e->getMessage();
        }
        echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    });
    require_once __DIR__ . '/src/includes/cors.php';
    require_once __DIR__ . '/src/config/database.php';
    require_once __DIR__ . '/src/routes/api.php';

    $router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
    exit;
}

// A raiz do projeto abre a tela de login. As outras páginas são servidas
// pelos arquivos PHP correspondentes (dashboard.php, materias.php etc.).
if (in_array($requestPath, ['', '/', '/index.php'], true)) {
    require __DIR__ . '/login.php';
    exit;
}

http_response_code(404);
header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Página não encontrada</title><h1>Página não encontrada</h1><p><a href="index.php">Voltar ao início</a></p></html>';
