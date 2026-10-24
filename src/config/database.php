
<?php


require_once __DIR__ . '/environment.php';

function ConectarDB(): PDO
{
    $host = dogsTeamConfig('DB_HOST', '127.0.0.1') ?: '127.0.0.1';
    $porta = dogsTeamConfig('DB_PORT', '3306') ?: '3306';
    $dbname = dogsTeamConfig('DB_NAME', 'dogs_team_db') ?: 'dogs_team_db';
    $user = dogsTeamConfig('DB_USER', 'root') ?? 'root';
    $pass = dogsTeamConfig('DB_PASS', '') ?? '';
    $charset = 'utf8mb4';

    $dsn = "mysql:host={$host};port={$porta};dbname={$dbname};charset={$charset}";

    try {
        return new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_FOUND_ROWS => true,
        ]);
    } catch (PDOException $e) {
        // Registra os detalhes apenas no log do servidor; não revela dados internos ao cliente.
        error_log('Dogs Team - falha ao conectar ao banco: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        $resposta = ['sucesso' => false, 'erro' => 'Não foi possível conectar ao banco de dados. Verifique a configuração local.'];
        if (dogsTeamConfig('DB_DEBUG', '0') === '1') {
            $resposta['detalhe'] = $e->getMessage();
        }
        echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
        exit;
    }
}