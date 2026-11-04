<?php

/** Erro que invalida o arquivo inteiro (por exemplo: front matter aberto e não fechado, nenhuma questão). */
class MarkdownImportException extends RuntimeException
{
}

/**
 * Converte o texto de um arquivo Markdown em um rascunho de simulado.
 *
 * Funciona somente sobre texto em memória: não lê nem grava arquivos, não executa nada do conteúdo
 * e não gera HTML. O resultado é um array simples que a camada de importação valida e devolve ao
 * cliente para pré-visualização.
 *
 * Formato aceito (resumo; o modelo completo está em modelos/modelo-simulado.md):
 *
 *   ---
 *   materia: Matemática
 *   assunto: Funções do 1º grau
 *   titulo: Revisão de funções
 *   duracao_minutos: 60
 *   ---
 *   ## Questão 1
 *   Enunciado (blocos de código e LaTeX são preservados como texto)
 *
 *   - a) texto
 *   - b) texto
 *
 *   **Tipo:** objetiva            (opcional: objetiva | discursiva | objetiva_justificativa)
 *   **Gabarito:** b
 *   **Pontos:** 1                 (opcional; padrão 1)
 *   **Explicação:** texto         (opcional)
 */
final class MarkdownSimuladoParser
{
    public const MAX_QUESTOES = 200;

    private const ROTULOS_CABECALHO = [
        'materia' => 'materia',
        'disciplina' => 'materia',
        'assunto' => 'assunto',
        'tema' => 'assunto',
        'topico' => 'assunto',
        'titulo' => 'titulo',
        'nome' => 'titulo',
        'duracao_minutos' => 'duracao_minutos',
        'duracao' => 'duracao_minutos',
        'tempo' => 'duracao_minutos',
        'descricao' => 'descricao',
    ];

    private const ROTULOS_QUESTAO = [
        'gabarito' => 'gabarito',
        'pontos' => 'pontos',
        'pontuacao' => 'pontos',
        'tipo' => 'tipo',
        'explicacao' => 'explicacao',
        'resolucao' => 'explicacao',
    ];

    private const TIPOS_POR_APELIDO = [
        'objetiva' => 'objetiva',
        'discursiva' => 'discursiva',
        'dissertativa' => 'discursiva',
        'aberta' => 'discursiva',
        'objetiva_justificativa' => 'objetiva_justificativa',
        'objetiva_com_justificativa' => 'objetiva_justificativa',
        'objetiva_e_justificativa' => 'objetiva_justificativa',
        'justificativa' => 'objetiva_justificativa',
    ];

    /**
     * @return array{meta: array<string, mixed>, questoes: array<int, array<string, mixed>>, avisos_gerais: array<int, string>}
     * @throws MarkdownImportException
     */
    public function interpretar(string $conteudo): array
    {
        $texto = str_replace(["\r\n", "\r"], "\n", $conteudo);
        if (str_starts_with($texto, "\xEF\xBB\xBF")) {
            $texto = substr($texto, 3);
        }
        $linhas = explode("\n", $texto);
        $total = count($linhas);
        $avisosGerais = [];

        // ---------------- Front matter ----------------
        $indice = 0;
        while ($indice < $total && trim($linhas[$indice]) === '') {
            $indice++;
        }
        $meta = [
            'materia' => null,
            'assunto' => null,
            'titulo' => null,
            'duracao_minutos' => null,
            'descricao' => null,
        ];
        if ($indice < $total && trim($linhas[$indice]) === '---') {
            $fim = null;
            for ($k = $indice + 1; $k < $total; $k++) {
                $t = trim($linhas[$k]);
                if ($t === '---' || $t === '...') {
                    $fim = $k;
                    break;
                }
            }
            if ($fim === null) {
                throw new MarkdownImportException(
                    'O front matter começa com “---” mas não foi fechado. Feche-o com outra linha “---” antes da primeira questão.'
                );
            }
            $this->lerCabecalho(array_slice($linhas, $indice + 1, $fim - $indice - 1), $meta, $avisosGerais);
            $indice = $fim + 1;
        } else {
            $avisosGerais[] = 'O arquivo não tem front matter (bloco “---” no início). Informe matéria e título na pré-visualização.';
        }

        // ---------------- Separação das questões ----------------
        $blocos = [];
        $atual = null;
        $preambulo = [];
        $cerca = null;
        for ($i = $indice; $i < $total; $i++) {
            $linha = $linhas[$i];
            $ehCerca = preg_match('/^\s{0,3}(`{3,}|~{3,})(.*)$/', $linha, $f) === 1;
            if ($cerca !== null) {
                if ($ehCerca && $f[1][0] === $cerca[0] && strlen($f[1]) >= $cerca[1] && trim($f[2]) === '') {
                    $cerca = null;
                }
            } elseif ($ehCerca) {
                $cerca = [$f[1][0], strlen($f[1])];
            } elseif (preg_match('/^\s{0,3}##\s+quest[aã]o(?!\p{L})(.*)$/iu', $linha, $m) === 1) {
                if ($atual !== null) {
                    $blocos[] = $atual;
                }
                if (count($blocos) >= self::MAX_QUESTOES) {
                    throw new MarkdownImportException(
                        'O arquivo tem mais de ' . self::MAX_QUESTOES . ' questões. Divida-o em arquivos menores.'
                    );
                }
                $atual = ['linha' => $i + 1, 'rotulo' => trim($m[1]), 'linhas' => []];
                continue;
            }
            if ($atual === null) {
                $preambulo[] = $linha;
            } else {
                $atual['linhas'][] = $linha;
            }
        }
        if ($atual !== null) {
            $blocos[] = $atual;
        }

        if ($blocos === []) {
            throw new MarkdownImportException(
                'Nenhuma questão encontrada. Cada questão deve começar com um título como “## Questão 1”.'
            );
        }
        if (trim(implode("\n", $this->aparar($preambulo))) !== '') {
            $avisosGerais[] = 'O texto entre o front matter e a primeira questão foi ignorado.';
        }

        $questoes = [];
        foreach ($blocos as $posicao => $bloco) {
            $questoes[] = $this->interpretarBloco($posicao + 1, $bloco);
        }

        return ['meta' => $meta, 'questoes' => $questoes, 'avisos_gerais' => $avisosGerais];
    }

    // ------------------------------------------------------------------
    // Cabeçalho
    // ------------------------------------------------------------------

    /** @param array<int, string> $linhas */
    private function lerCabecalho(array $linhas, array &$meta, array &$avisos): void
    {
        $vistos = [];
        foreach ($linhas as $linha) {
            if (trim($linha) === '' || str_starts_with(ltrim($linha), '#')) {
                continue;
            }
            if (preg_match('/^([^:]{1,40}):\s*(.*)$/u', $linha, $m) !== 1) {
                $avisos[] = 'Linha ignorada no front matter (use “campo: valor”): ' . $this->trecho($linha);
                continue;
            }
            $rotulo = preg_replace('/[^a-z0-9]+/', '_', $this->semAcentos($m[1]));
            $rotulo = trim((string) $rotulo, '_');
            $campo = self::ROTULOS_CABECALHO[$rotulo] ?? null;
            if ($campo === null) {
                $avisos[] = 'Campo desconhecido no front matter ignorado: ' . $this->trecho($m[1]);
                continue;
            }
            $valor = trim($m[2]);
            if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && substr($valor, -1) === $valor[0]) {
                $valor = trim(substr($valor, 1, -1));
            }
            if (isset($vistos[$campo])) {
                $avisos[] = 'O campo “' . $campo . '” aparece mais de uma vez no front matter; vale o último.';
            }
            $vistos[$campo] = true;

            if ($campo === 'duracao_minutos') {
                if ($valor === '') {
                    $meta[$campo] = null;
                } elseif (preg_match('/^(\d{1,4})\s*(?:min(?:utos?)?)?$/iu', $valor, $d) === 1) {
                    $meta[$campo] = (int) $d[1];
                } else {
                    $meta[$campo] = null;
                    $avisos[] = 'duracao_minutos inválido (' . $this->trecho($valor) . '); informe um número inteiro de minutos.';
                }
                continue;
            }
            $meta[$campo] = $valor === '' ? null : $valor;
        }
    }

    // ------------------------------------------------------------------
    // Questões
    // ------------------------------------------------------------------

    /**
     * @param array{linha: int, rotulo: string, linhas: array<int, string>} $bloco
     * @return array<string, mixed>
     */
    private function interpretarBloco(int $numero, array $bloco): array
    {
        $linhas = $this->aparar($bloco['linhas']);
        $avisos = [];

        $estado = 'enunciado';       // enunciado | alternativa | pos_alternativas | campo
        $cerca = null;
        $enunciado = [];
        $alternativas = [];          // cada item: ['letra' => string, 'linhas' => string[]]
        $alternativaAtual = null;    // índice em $alternativas
        $campos = [];                // campo => string[]
        $campoAtual = null;
        $ignoradas = 0;

        foreach ($linhas as $linha) {
            $ehCerca = preg_match('/^\s{0,3}(`{3,}|~{3,})(.*)$/', $linha, $f) === 1;

            if ($cerca !== null) {
                if (!$this->acrescentar($linha, $estado, $enunciado, $alternativas, $alternativaAtual, $campos, $campoAtual)) {
                    $ignoradas++;
                }
                if ($ehCerca && $f[1][0] === $cerca[0] && strlen($f[1]) >= $cerca[1] && trim($f[2]) === '') {
                    $cerca = null;
                }
                continue;
            }
            if ($ehCerca) {
                $cerca = [$f[1][0], strlen($f[1])];
                if (!$this->acrescentar($linha, $estado, $enunciado, $alternativas, $alternativaAtual, $campos, $campoAtual)) {
                    $ignoradas++;
                }
                continue;
            }

            // Campos **Gabarito:**, **Pontos:**, **Tipo:**, **Explicação:**
            if (preg_match('/^\s{0,3}\*\*\s*([^*:]{1,30}?)\s*(?::\s*\*\*|\*\*\s*:)\s*(.*)$/u', $linha, $m) === 1) {
                $rotulo = trim((string) preg_replace('/[^a-z0-9]+/', '_', $this->semAcentos($m[1])), '_');
                $campo = self::ROTULOS_QUESTAO[$rotulo] ?? null;
                if ($campo !== null) {
                    if (isset($campos[$campo])) {
                        $avisos[] = 'O campo “' . $m[1] . '” aparece mais de uma vez; vale o último.';
                    }
                    $campos[$campo] = [$m[2]];
                    $alternativaAtual = null;
                    // Tipo, Pontos e Gabarito ocupam uma linha e podem vir antes do enunciado ou das
                    // alternativas, então não mudam o estado. Só a Explicação abre um bloco de várias linhas.
                    if ($campo === 'explicacao' || $estado === 'campo') {
                        $campoAtual = $campo;
                        $estado = 'campo';
                    } elseif ($estado === 'alternativa') {
                        $estado = 'pos_alternativas';
                    }
                    continue;
                }
            }

            // Alternativas: "- a) texto" (também "- A. texto"). Dentro de um campo (explicação) não valem.
            if ($estado !== 'campo' && preg_match('/^\s{0,3}[-*+]\s+\(?([A-Za-z])[\).](?:\s+(.*))?$/u', $linha, $m) === 1) {
                $alternativas[] = ['letra' => strtolower($m[1]), 'linhas' => [$m[2] ?? '']];
                $alternativaAtual = count($alternativas) - 1;
                $estado = 'alternativa';
                continue;
            }

            if ($estado === 'enunciado') {
                $enunciado[] = $linha;
            } elseif ($estado === 'alternativa') {
                if (trim($linha) === '') {
                    $alternativaAtual = null;
                    $estado = 'pos_alternativas';
                } else {
                    // Continuação (indentada ou "preguiçosa") do texto da alternativa.
                    $alternativas[$alternativaAtual]['linhas'][] = trim($linha);
                }
            } elseif ($estado === 'campo') {
                if ($campoAtual === 'explicacao') {
                    $campos['explicacao'][] = $linha;
                } elseif (trim($linha) !== '') {
                    $ignoradas++;
                }
            } elseif (trim($linha) !== '') { // pos_alternativas
                $ignoradas++;
            }
        }

        if ($cerca !== null) {
            $avisos[] = 'Há um bloco de código sem fechamento (``` ou ~~~); o restante da questão pode ter sido interpretado como código.';
        }
        if ($ignoradas > 0) {
            $avisos[] = $ignoradas . ' linha(s) fora do padrão (depois das alternativas ou de um campo) foram ignoradas.';
        }

        // ---------------- Tipo ----------------
        $tipoInformado = isset($campos['tipo']) ? trim((string) $campos['tipo'][0]) : '';
        $tipo = 'objetiva';
        if ($tipoInformado !== '') {
            $chave = trim((string) preg_replace('/[^a-z0-9]+/', '_', $this->semAcentos(str_replace(['*', '`'], '', $tipoInformado))), '_');
            if (isset(self::TIPOS_POR_APELIDO[$chave])) {
                $tipo = self::TIPOS_POR_APELIDO[$chave];
            } else {
                $avisos[] = 'Tipo “' . $this->trecho($tipoInformado) . '” não reconhecido; foi usado “objetiva”. Confira o tipo na pré-visualização.';
            }
        }

        // ---------------- Pontos ----------------
        $pontos = 1.0;
        if (isset($campos['pontos'])) {
            $bruto = trim(str_replace(['*', '`'], '', (string) $campos['pontos'][0]));
            $bruto = trim((string) preg_replace('/\s*(?:pontos?|pts?)\.?$/iu', '', $bruto));
            if ($bruto === '') {
                $avisos[] = 'O campo Pontos está vazio; foi usado 1.';
            } elseif (preg_match('/^\d+(?:[.,]\d{1,2})?$/', $bruto) === 1) {
                $pontos = (float) str_replace(',', '.', $bruto);
            } else {
                $pontos = null;
                $avisos[] = 'Pontos “' . $this->trecho($bruto) . '” não é um número válido.';
            }
        }

        // ---------------- Alternativas e gabarito ----------------
        $temAlternativas = $tipo !== 'discursiva';
        $gabaritoInformado = null;
        if (isset($campos['gabarito'])) {
            $bruto = trim(str_replace(['*', '`'], '', (string) $campos['gabarito'][0]));
            $gabaritoInformado = $bruto === '' ? null : $bruto;
        }

        $saida = [];
        $letras = [];
        if ($temAlternativas) {
            foreach ($alternativas as $alt) {
                $saida[] = ['texto' => trim(implode("\n", $alt['linhas'])), 'correta' => false];
                $letras[] = $alt['letra'];
            }
            if (count(array_unique($letras)) !== count($letras)) {
                $avisos[] = 'Há letras repetidas nas alternativas; o gabarito pode ficar ambíguo.';
            }
            $sequencia = array_slice(range('a', 'z'), 0, count($letras));
            if ($letras !== [] && $letras !== $sequencia) {
                $avisos[] = 'As letras das alternativas não seguem a sequência a, b, c…; o sistema as reorganiza nessa ordem.';
            }

            $letrasCorretas = $gabaritoInformado === null ? [] : $this->letrasDoGabarito($gabaritoInformado);
            foreach ($letrasCorretas as $letra) {
                if (!in_array($letra, $letras, true)) {
                    $avisos[] = 'A letra “' . $letra . '” do gabarito não corresponde a nenhuma alternativa.';
                }
            }
            foreach ($letras as $pos => $letra) {
                if (in_array($letra, $letrasCorretas, true)) {
                    $saida[$pos]['correta'] = true;
                }
            }
            if ($alternativas === [] && $tipoInformado === '') {
                $avisos[] = 'Questão sem alternativas. Se for discursiva, acrescente “**Tipo:** discursiva”.';
            }
        } else {
            if ($alternativas !== []) {
                $avisos[] = 'Alternativas ignoradas: questões discursivas não têm alternativas.';
            }
            if ($gabaritoInformado !== null) {
                $avisos[] = 'Gabarito ignorado: questões discursivas são corrigidas manualmente. Use a Explicação para registrar a resposta esperada.';
                $gabaritoInformado = null;
            }
        }

        // ---------------- Explicação ----------------
        $explicacao = null;
        if (isset($campos['explicacao'])) {
            $texto = trim(implode("\n", $campos['explicacao']), "\n ");
            $explicacao = trim($texto) === '' ? null : trim($texto);
        }

        return [
            'numero' => $numero,
            'linha' => $bloco['linha'],
            'rotulo' => $this->trecho($bloco['rotulo']),
            'tipo' => $tipo,
            'enunciado' => trim(implode("\n", $enunciado)),
            'pontos' => $pontos,
            'alternativas' => $saida,
            'explicacao' => $explicacao,
            'gabarito_informado' => $gabaritoInformado,
            'avisos' => $avisos,
        ];
    }

    /**
     * Acrescenta uma linha de código (dentro de cerca) ao bloco que está sendo preenchido.
     * Retorna false quando a linha não pertence a nenhum campo (por exemplo, código solto depois das alternativas).
     */
    private function acrescentar(
        string $linha,
        string $estado,
        array &$enunciado,
        array &$alternativas,
        ?int $alternativaAtual,
        array &$campos,
        ?string $campoAtual
    ): bool {
        if ($estado === 'alternativa' && $alternativaAtual !== null) {
            $alternativas[$alternativaAtual]['linhas'][] = $linha;
            return true;
        }
        if ($estado === 'campo' && $campoAtual === 'explicacao') {
            $campos['explicacao'][] = $linha;
            return true;
        }
        if ($estado === 'enunciado') {
            $enunciado[] = $linha;
            return true;
        }
        return false;
    }

    /**
     * Converte o valor do campo Gabarito em letras minúsculas. Aceita "b", "B)", "(b)", "b) texto"
     * e, para detectar erro de cadastro, listas como "b, c" ou "b e c".
     *
     * @return array<int, string>
     */
    private function letrasDoGabarito(string $valor): array
    {
        $valor = trim($valor);

        // Várias letras ("b, c", "b e c"): serve para o validador acusar "mais de uma correta".
        $partes = preg_split('/\s*(?:,|;|\/|\s+e\s+|\s+ou\s+)\s*/iu', $valor);
        if (is_array($partes) && count($partes) >= 2) {
            $letras = [];
            foreach ($partes as $parte) {
                if (preg_match('/^\(?([A-Za-z])\)?$/', trim($parte), $m) !== 1) {
                    $letras = [];
                    break;
                }
                $letras[] = strtolower($m[1]);
            }
            if ($letras !== []) {
                return array_values(array_unique($letras));
            }
        }

        // Uma letra, opcionalmente seguida de texto: "b", "B)", "(b)", "b) 8".
        if (preg_match('/^\(?([A-Za-z])\)?[.:\-)]?(?:\s.*)?$/u', $valor, $m) === 1) {
            return [strtolower($m[1])];
        }
        return [];
    }

    /** Remove linhas em branco e separadores horizontais (---, ***) do fim do bloco. */
    private function aparar(array $linhas): array
    {
        while ($linhas !== []) {
            $ultima = $linhas[array_key_last($linhas)];
            if (trim($ultima) === '' || preg_match('/^\s{0,3}([-*_])(?:\s*\1){2,}\s*$/', $ultima) === 1) {
                array_pop($linhas);
            } else {
                break;
            }
        }
        return $linhas;
    }

    private function semAcentos(string $texto): string
    {
        static $mapa = [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ];
        $texto = mb_strtolower($texto, 'UTF-8');
        // Remove acentos "soltos" (forma decomposta) e depois os precompostos.
        $texto = (string) preg_replace('/\p{Mn}+/u', '', $texto);
        return strtr($texto, $mapa);
    }

    /** Trecho curto e seguro (sem quebrar caracteres multibyte) para mensagens ao usuário. */
    private function trecho(string $texto, int $limite = 60): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        return mb_strlen($texto, 'UTF-8') > $limite ? mb_substr($texto, 0, $limite, 'UTF-8') . '…' : $texto;
    }
}
