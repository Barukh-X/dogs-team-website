<?php


require_once __DIR__ . '/../config/environment.php';

class JwtService
{
    private string $secret;
    private string $algo = 'HS256';

    public function __construct(?string $secret = null)
    {
        $isLocal = $this->isLocalDevelopment();
        $placeholder = 'SUBSTITUA_POR_UMA_CHAVE_ALEATORIA_LONGA_ANTES_DE_PUBLICAR';
        $configured = $secret ?? (dogsTeamConfig('JWT_SECRET', '') ?? '');
        $configured = trim($configured);

        // A chave de desenvolvimento existe somente para instalações locais.
        // Em produção, uma chave conhecida permitiria falsificar tokens administrativos.
        if ($configured === $placeholder) {
            $configured = '';
        }
        if ($configured === '') {
            if (!$isLocal) {
                throw new RuntimeException('JWT_SECRET deve ser configurado no ambiente de produção.');
            }
            $configured = 'dogs-team-local-development-only-change-this-secret';
        }
        if (!$isLocal && strlen($configured) < 32) {
            throw new RuntimeException('JWT_SECRET deve ter pelo menos 32 bytes em produção.');
        }

        $this->secret = $configured;
    }

    private function isLocalDevelopment(): bool
    {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            return true;
        }
        $host = strtolower((string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST));
        $host = trim($host, '[]');
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    public function gerar(array $payload, int $expiraEmSegundos = 3600): string
    {
        $header = ['typ' => 'JWT', 'alg' => $this->algo];
        $payload['iat'] = time();
        $payload['exp'] = time() + $expiraEmSegundos;

        $headerCodificado = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $payloadCodificado = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $assinatura = hash_hmac('sha256', "$headerCodificado.$payloadCodificado", $this->secret, true);
        $assinaturaCodificada = $this->base64UrlEncode($assinatura);

        return "$headerCodificado.$payloadCodificado.$assinaturaCodificada";
    }

    public function validar(string $token): ?array
    {
        $partes = explode('.', $token);
        if (count($partes) !== 3) {
            return null;
        }

        [$headerCodificado, $payloadCodificado, $assinaturaCodificada] = $partes;
        $headerJson = $this->base64UrlDecode($headerCodificado);
        $header = $headerJson === false ? null : json_decode($headerJson, true);
        if (!is_array($header) || ($header['alg'] ?? null) !== $this->algo) {
            return null;
        }

        $assinaturaEsperada = hash_hmac('sha256', "$headerCodificado.$payloadCodificado", $this->secret, true);
        if (!hash_equals($this->base64UrlEncode($assinaturaEsperada), $assinaturaCodificada)) {
            return null;
        }

        $payloadJson = $this->base64UrlDecode($payloadCodificado);
        $payload = $payloadJson === false ? null : json_decode($payloadJson, true);
        if (!is_array($payload) || !isset($payload['exp'], $payload['sub']) || !is_numeric($payload['exp']) || (int) $payload['exp'] <= time()) {
            return null;
        }

        return $payload;
    }

    private function base64UrlEncode(string|false $dados): string
    {
        if ($dados === false) {
            throw new RuntimeException('Não foi possível codificar o token.');
        }
        return rtrim(strtr(base64_encode($dados), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $dados): string|false
    {
        $resto = strlen($dados) % 4;
        $padded = $resto ? str_pad($dados, strlen($dados) + (4 - $resto), '=') : $dados;
        return base64_decode(strtr($padded, '-_', '+/'), true);
    }
}
