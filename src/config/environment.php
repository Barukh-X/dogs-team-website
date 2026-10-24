<?php

/** Armazena as variáveis do .env em memória (não depende de putenv/getenv, que podem estar desativados). */
function dogsTeamEnvStore(?array $novo = null): array
{
    static $dados = [];
    if ($novo !== null) {
        $dados = $novo;
    }
    return $dados;
}

/** Carrega variáveis de um arquivo .env sem depender de bibliotecas externas. */
function carregarVariaveisAmbiente(): void
{
    static $carregado = false;
    if ($carregado) {
        return;
    }
    $carregado = true;

    $arquivo = dirname(__DIR__, 2) . '/.env';
    if (!is_file($arquivo) || !is_readable($arquivo)) {
        return;
    }

    $conteudo = file_get_contents($arquivo);
    if ($conteudo === false) {
        return;
    }
    $conteudo = preg_replace('/^\xEF\xBB\xBF/', '', $conteudo);
    $linhas = preg_split('/\R/', $conteudo);

    $dados = [];
    foreach ($linhas as $linha) {
        $linha = trim($linha);
        if ($linha === '' || $linha[0] === '#' || strpos($linha, '=') === false) {
            continue;
        }

        [$nome, $valor] = explode('=', $linha, 2);
        $nome = trim($nome);
        $valor = trim($valor);
        if ($nome === '') {
            continue;
        }

        if (strlen($valor) >= 2) {
            $primeiro = $valor[0];
            $ultimo = $valor[strlen($valor) - 1];
            if (($primeiro === '"' && $ultimo === '"') || ($primeiro === "'" && $ultimo === "'")) {
                $valor = substr($valor, 1, -1);
            }
        }

        $dados[$nome] = $valor;
    }

    dogsTeamEnvStore($dados);
}

/** Lê uma configuração do .env ou do ambiente, preservando valores vazios válidos. */
function dogsTeamConfig(string $nome, ?string $padrao = null): ?string
{
    carregarVariaveisAmbiente();
    $dados = dogsTeamEnvStore();
    if (array_key_exists($nome, $dados)) {
        return $dados[$nome];
    }
    $valor = getenv($nome);
    return $valor === false ? $padrao : $valor;
}

carregarVariaveisAmbiente();
