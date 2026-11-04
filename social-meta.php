<?php
/**
 * Metadados de compartilhamento para WhatsApp, Facebook, Telegram e X.
 * Inclua este arquivo dentro do <head> das páginas públicas.
 */

$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
    $host = 'localhost';
}

$isHttps = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
$scheme = $isHttps ? 'https' : 'http';

$scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/')));
$basePath = $scriptDirectory === '/' || $scriptDirectory === '.' ? '' : rtrim($scriptDirectory, '/');

$requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$requestPath = is_string($requestPath) && $requestPath !== '' ? $requestPath : '/';
if ($requestPath === '/index.php') {
    $requestPath = '/';
}

$siteOrigin = $scheme . '://' . $host;
$siteUrl = $siteOrigin . $basePath;
$indexPath = ($basePath !== '' ? $basePath : '') . '/index.php';
if ($requestPath === $indexPath) {
    $requestPath = ($basePath !== '' ? $basePath : '') . '/';
}
$canonicalUrl = $siteOrigin . $requestPath;
$previewImageUrl = $siteUrl . '/imagens/preview-estudos-computacao.jpg';

$socialTitle = $socialTitle ?? 'Dogs Team — Estudos acadêmicos e computação';
$socialDescription = $socialDescription ?? 'Seu espaço para organizar estudos, matemática, ciência, tecnologia e programação.';

$escapeMeta = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<link rel="canonical" href="<?= $escapeMeta($canonicalUrl) ?>">
<meta name="description" content="<?= $escapeMeta($socialDescription) ?>">
<meta property="og:locale" content="pt_BR">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Dogs Team">
<meta property="og:title" content="<?= $escapeMeta($socialTitle) ?>">
<meta property="og:description" content="<?= $escapeMeta($socialDescription) ?>">
<meta property="og:url" content="<?= $escapeMeta($canonicalUrl) ?>">
<meta property="og:image" content="<?= $escapeMeta($previewImageUrl) ?>">
<meta property="og:image:secure_url" content="<?= $escapeMeta($previewImageUrl) ?>">
<meta property="og:image:type" content="image/jpeg">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="1200">
<meta property="og:image:alt" content="Ilustração em tons de roxo sobre estudos acadêmicos, matemática, ciência e computação">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= $escapeMeta($socialTitle) ?>">
<meta name="twitter:description" content="<?= $escapeMeta($socialDescription) ?>">
<meta name="twitter:image" content="<?= $escapeMeta($previewImageUrl) ?>">
<meta name="twitter:image:alt" content="Ilustração em tons de roxo sobre estudos acadêmicos, matemática, ciência e computação">
