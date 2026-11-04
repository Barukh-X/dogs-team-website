<?php

require_once __DIR__ . '/../models/Simulado.php';
require_once __DIR__ . '/SimuladoService.php';
require_once __DIR__ . '/MarkdownSimuladoParser.php';

/** Falha de validação com a lista de problemas por questão (HTTP 422). */
class SimuladoImportacaoInvalidaException extends SimuladoApiException
{
    /** @var array<int, array{numero: int, erros: array<int, string>}> */
    public array $detalhes;

    public function __construct(string $mensagem, array $detalhes)
    {
        parent::__construct($mensagem, 422);
        $this->detalhes = $detalhes;
    }
}

/**
 * Importação de simulados a partir de arquivos Markdown (sem IA).
 *
 * Fluxo em duas etapas, ambas somente para administradores:
 *  1. analisar(): recebe o upload, valida (extensão, MIME, tamanho, UTF-8), interpreta o texto em memória
 *     e devolve um rascunho com erros e avisos por questão. NADA é gravado.
 *  2. confirmar(): recebe o rascunho revisado/editado pelo administrador, valida tudo de novo no servidor
 *     (o cliente nunca é fonte de verdade) e cria o simulado como rascunho, em uma única transação.
 *
 * O arquivo enviado nunca é movido nem salvo pela aplicação: é lido do arquivo temporário do PHP
 * (apagado automaticamente ao fim da requisição) e processado em memória.
 */
class SimuladoImportacaoService
{
    public const MAX_BYTES = 1048576; // 1 MiB
    public const MAX_QUESTOES = MarkdownSimuladoParser::MAX_QUESTOES;
    public const EXTENSOES_PERMITIDAS = ['md', 'txt'];

    private const MAX_ENUNCIADO = 20000;
    private const MAX_ALTERNATIVA = 4000;
    private const MAX_EXPLICACAO = 20000;

    private Simulado $modelo;
    private SimuladoService $simulados;
    private MarkdownSimuladoParser $parser;

    public function __construct(PDO $pdo)
    {
        $this->modelo = new Simulado($pdo);
        $this->simulados = new SimuladoService($pdo);
        $this->parser = new MarkdownSimuladoParser();
    }

    // ------------------------------------------------------------------
    // Etapa 1: validar e pré-visualizar (não grava nada)
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $arquivos conteúdo de $_FILES */
    public function analisar(array $arquivos, bool $admin): array
    {
        $this->exigirAdmin($admin);
        [$nome, $conteudo] = $this->lerUpload($arquivos['arquivo'] ?? null);

        try {
            $bruto = $this->parser->interpretar($conteudo);
        } catch (MarkdownImportException $e) {
            throw new SimuladoApiException($e->getMessage(), 422);
        }

        $meta = $bruto['meta'];
        $materia = null;
        if (is_string($meta['materia']) && $meta['materia'] !== '') {
            $materia = $this->modelo->buscarMateriaPorNome(mb_substr($meta['materia'], 0, 100, 'UTF-8'));
        }
        $titulo = $meta['titulo'] ?? $meta['assunto'];

        // O assunto não tem coluna própria: entra no início da descrição (editável na pré-visualização).
        $partesDescricao = [];
        if (is_string($meta['assunto']) && $meta['assunto'] !== '') {
            $partesDescricao[] = 'Assunto: ' . $meta['assunto'];
        }
        if (is_string($meta['descricao']) && $meta['descricao'] !== '') {
            $partesDescricao[] = $meta['descricao'];
        }
        $descricao = $partesDescricao === [] ? null : implode("\n\n", $partesDescricao);

        $pendencias = [];
        if (!is_string($titulo) || trim($titulo) === '') {
            $pendencias[] = 'Informe o título do simulado.';
        }
        if ($materia === null) {
            $pendencias[] = is_string($meta['materia']) && $meta['materia'] !== ''
                ? 'A matéria “' . $meta['materia'] . '” não foi encontrada. Escolha uma matéria existente.'
                : 'O arquivo não informa a matéria. Escolha uma matéria existente.';
        }

        $questoes = [];
        $comErros = 0;
        foreach ($bruto['questoes'] as $questao) {
            $questao['erros'] = $this->validarRascunho($questao);
            if ($questao['erros'] !== []) {
                $comErros++;
            }
            $questoes[] = $questao;
        }

        return [
            'arquivo' => ['nome' => $nome, 'tamanho' => strlen($conteudo)],
            'simulado' => [
                'titulo' => is_string($titulo) ? $titulo : null,
                'assunto' => $meta['assunto'],
                'descricao' => $descricao,
                'duracao_minutos' => $meta['duracao_minutos'],
                'materia_informada' => $meta['materia'],
                'id_materia' => $materia === null ? null : $materia['id'],
                'materia_encontrada' => $materia !== null,
            ],
            'pendencias' => $pendencias,
            'avisos_gerais' => $bruto['avisos_gerais'],
            'questoes' => $questoes,
            'totais' => ['questoes' => count($questoes), 'com_erros' => $comErros],
            'limites' => [
                'max_bytes' => self::MAX_BYTES,
                'max_questoes' => self::MAX_QUESTOES,
                'min_alternativas' => 2,
                'max_alternativas' => 10,
            ],
        ];
    }

    // ------------------------------------------------------------------
    // Etapa 2: confirmar e gravar (rascunho, em transação)
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $dados titulo, id_materia, descricao, duracao_minutos e questoes[]
     * @return array{id: int, total_questoes: int}
     */
    public function confirmar(array $dados, int $usuarioId, bool $admin): array
    {
        $this->exigirAdmin($admin);

        $questoes = $dados['questoes'] ?? null;
        if (!is_array($questoes) || $questoes === []) {
            throw new SimuladoApiException('Inclua pelo menos uma questão para importar.', 422);
        }
        if (count($questoes) > self::MAX_QUESTOES) {
            throw new SimuladoApiException('O simulado importado pode ter no máximo ' . self::MAX_QUESTOES . ' questões.', 422);
        }

        // Dados gerais: mesmas regras do cadastro manual (matéria existente, título, descrição, duração).
        [$materiaId, , $titulo, $descricao, $duracao] = $this->simulados->normalizarDadosGeraisImportados([
            'id_materia' => $dados['id_materia'] ?? null,
            'titulo' => $dados['titulo'] ?? null,
            'descricao' => $dados['descricao'] ?? null,
            'duracao_minutos' => $dados['duracao_minutos'] ?? null,
        ]);

        // Questões: validação completa de todas antes de gravar qualquer coisa.
        $normalizadas = [];
        $falhas = [];
        foreach (array_values($questoes) as $indice => $questao) {
            $numero = $indice + 1;
            if (!is_array($questao)) {
                $falhas[] = ['numero' => $numero, 'erros' => ['Formato de questão inválido.']];
                continue;
            }
            $erros = $this->validarRascunho($questao);
            if ($erros === []) {
                try {
                    // Autoridade final: as mesmas regras de POST /api/simulados/questoes.
                    $normalizadas[] = $this->simulados->normalizarQuestaoImportada($questao, $numero);
                } catch (SimuladoApiException $e) {
                    $erros[] = $e->getMessage();
                }
            }
            if ($erros !== []) {
                $falhas[] = ['numero' => $numero, 'erros' => $erros];
            }
        }
        if ($falhas !== []) {
            throw new SimuladoImportacaoInvalidaException(
                'Corrija os erros indicados nas questões antes de confirmar a importação.',
                $falhas
            );
        }

        $id = $this->modelo->criarComQuestoes($materiaId, null, $usuarioId, $titulo, $descricao, $duracao, $normalizadas);
        return ['id' => $id, 'total_questoes' => count($normalizadas)];
    }

    // ------------------------------------------------------------------
    // Validação de uma questão (devolve TODAS as mensagens, não só a primeira)
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $q questão no formato do rascunho
     * @return array<int, string>
     */
    public function validarRascunho(array $q): array
    {
        $erros = [];

        $enunciado = is_string($q['enunciado'] ?? null) ? trim($q['enunciado']) : '';
        if ($enunciado === '') {
            $erros[] = 'Enunciado vazio.';
        } elseif (mb_strlen($enunciado, 'UTF-8') > self::MAX_ENUNCIADO) {
            $erros[] = 'O enunciado excede 20.000 caracteres.';
        }

        $tipo = $q['tipo'] ?? 'objetiva';
        if (!is_string($tipo) || !in_array($tipo, Simulado::TIPOS, true)) {
            $erros[] = 'Tipo de questão inválido (use objetiva, discursiva ou objetiva_justificativa).';
            $tipo = null;
        }

        $pontos = $q['pontos'] ?? null;
        if (!is_numeric($pontos) || !is_finite((float) $pontos) || (float) $pontos <= 0 || (float) $pontos > 100) {
            $erros[] = 'Pontos inválidos: informe um valor maior que zero e no máximo 100.';
        }

        $explicacao = $q['explicacao'] ?? null;
        if ($explicacao !== null && !is_string($explicacao)) {
            $erros[] = 'A explicação deve ser texto.';
        } elseif (is_string($explicacao) && mb_strlen(trim($explicacao), 'UTF-8') > self::MAX_EXPLICACAO) {
            $erros[] = 'A explicação excede 20.000 caracteres.';
        }

        $alternativas = $q['alternativas'] ?? [];
        if ($alternativas === null) {
            $alternativas = [];
        }
        if (!is_array($alternativas)) {
            $erros[] = 'Formato de alternativas inválido.';
            return $erros;
        }

        if ($tipo !== null && !in_array($tipo, Simulado::TIPOS_COM_ALTERNATIVAS, true)) {
            if ($alternativas !== []) {
                $erros[] = 'Questões discursivas não podem ter alternativas.';
            }
            return $erros;
        }
        if ($tipo === null) {
            return $erros;
        }

        $quantidade = count($alternativas);
        if ($quantidade < 2) {
            $erros[] = $quantidade === 0
                ? 'Sem alternativas: objetivas precisam de 2 a 10 alternativas.'
                : 'Menos de 2 alternativas (mínimo: 2).';
        } elseif ($quantidade > 10) {
            $erros[] = 'Mais de 10 alternativas (máximo: 10).';
        }

        $corretas = [];
        foreach (array_values($alternativas) as $posicao => $alternativa) {
            $letra = chr(65 + ($posicao % 26));
            if (!is_array($alternativa)) {
                $erros[] = 'Alternativa ' . $letra . ' com formato inválido.';
                continue;
            }
            $texto = is_string($alternativa['texto'] ?? null) ? trim($alternativa['texto']) : '';
            if ($texto === '') {
                $erros[] = 'Alternativa ' . $letra . ' sem texto.';
            } elseif (mb_strlen($texto, 'UTF-8') > self::MAX_ALTERNATIVA) {
                $erros[] = 'A alternativa ' . $letra . ' excede 4.000 caracteres.';
            }
            if (!is_bool($alternativa['correta'] ?? null)) {
                $erros[] = 'Alternativa ' . $letra . ': informe correta como verdadeiro ou falso.';
            } elseif ($alternativa['correta']) {
                $corretas[] = $letra;
            }
        }

        if (count($corretas) > 1) {
            $erros[] = 'Mais de uma alternativa correta (' . implode(', ', $corretas) . '); deve haver exatamente uma.';
        } elseif ($corretas === [] && $quantidade > 0) {
            // Rascunhos vindos do parser trazem "gabarito_informado": distingue gabarito ausente de inválido.
            if (array_key_exists('gabarito_informado', $q)) {
                $erros[] = $q['gabarito_informado'] === null
                    ? 'Gabarito ausente: informe a alternativa correta (por exemplo, “**Gabarito:** b”).'
                    : 'O gabarito “' . $this->trecho((string) $q['gabarito_informado']) . '” não corresponde a nenhuma alternativa.';
            } else {
                $erros[] = 'Nenhuma alternativa está marcada como correta.';
            }
        }

        return $erros;
    }

    // ------------------------------------------------------------------
    // Upload: extensão, tamanho, MIME real e UTF-8, tudo em memória
    // ------------------------------------------------------------------

    /**
     * @return array{0: string, 1: string} [nome seguro para exibição, conteúdo UTF-8 sem BOM]
     */
    private function lerUpload(mixed $arquivo): array
    {
        if (!is_array($arquivo) || !isset($arquivo['error']) || is_array($arquivo['error'])) {
            // Corpo maior que post_max_size: o PHP descarta o multipart inteiro e $_FILES chega vazio.
            if (($arquivo === null) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
                throw new SimuladoApiException('O envio excede o limite do servidor (post_max_size). Use um arquivo menor que 1 MB.', 413);
            }
            throw new SimuladoApiException('Envie um único arquivo .md ou .txt no campo “arquivo”.', 400);
        }

        $erro = (int) $arquivo['error'];
        if ($erro === UPLOAD_ERR_INI_SIZE || $erro === UPLOAD_ERR_FORM_SIZE) {
            throw new SimuladoApiException('O arquivo excede o limite de 1 MB.', 413);
        }
        if ($erro === UPLOAD_ERR_NO_FILE) {
            throw new SimuladoApiException('Nenhum arquivo foi enviado.', 400);
        }
        if ($erro !== UPLOAD_ERR_OK) {
            throw new SimuladoApiException('Falha no envio do arquivo. Tente novamente.', 400);
        }

        $nomeOriginal = is_string($arquivo['name'] ?? null) ? $arquivo['name'] : '';
        $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
        if (!in_array($extensao, self::EXTENSOES_PERMITIDAS, true)) {
            throw new SimuladoApiException('Extensão não permitida. Envie um arquivo .md ou .txt.', 422);
        }

        $temporario = $arquivo['tmp_name'] ?? null;
        if (!is_string($temporario) || $temporario === '' || !is_uploaded_file($temporario)) {
            throw new SimuladoApiException('O arquivo enviado não é válido.', 400);
        }

        // O tamanho real vem do arquivo no servidor, não do valor informado pelo navegador.
        $tamanho = filesize($temporario);
        if ($tamanho === false || $tamanho === 0) {
            throw new SimuladoApiException('O arquivo está vazio.', 422);
        }
        if ($tamanho > self::MAX_BYTES) {
            throw new SimuladoApiException('O arquivo excede o limite de 1 MB.', 413);
        }

        $conteudo = file_get_contents($temporario, false, null, 0, self::MAX_BYTES + 1);
        if (!is_string($conteudo) || $conteudo === '') {
            throw new SimuladoApiException('Não foi possível ler o arquivo enviado.', 400);
        }
        if (strlen($conteudo) > self::MAX_BYTES) {
            throw new SimuladoApiException('O arquivo excede o limite de 1 MB.', 413);
        }

        // MIME real (não confia no tipo informado pelo navegador): precisa ser texto.
        if (str_contains($conteudo, "\0")) {
            throw new SimuladoApiException('O arquivo contém dados binários e não é um texto Markdown.', 422);
        }
        if (class_exists('finfo')) {
            $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($conteudo);
            $mimesTextuais = ['application/json', 'application/xml', 'application/javascript', 'application/x-empty'];
            if (!is_string($mime) || (!str_starts_with($mime, 'text/') && !in_array($mime, $mimesTextuais, true))) {
                throw new SimuladoApiException(
                    'O conteúdo não é um arquivo de texto (tipo detectado: ' . (is_string($mime) ? $mime : 'desconhecido') . ').',
                    422
                );
            }
        }

        if (str_starts_with($conteudo, "\xEF\xBB\xBF")) {
            $conteudo = substr($conteudo, 3);
        }
        if (preg_match('//u', $conteudo) !== 1) {
            throw new SimuladoApiException('O arquivo não está em UTF-8. Salve-o com a codificação UTF-8 e tente de novo.', 422);
        }

        return [$this->nomeSeguro($nomeOriginal), $conteudo];
    }

    /** Nome apenas para exibição: sem caminho, sem caracteres de controle, tamanho limitado. */
    private function nomeSeguro(string $nome): string
    {
        $nome = basename(str_replace('\\', '/', $nome));
        $nome = (string) preg_replace('/[\x00-\x1F\x7F]+/u', '', $nome);
        if ($nome === '' || preg_match('//u', $nome) !== 1) {
            return 'arquivo.md';
        }
        return mb_strlen($nome, 'UTF-8') > 120 ? mb_substr($nome, 0, 120, 'UTF-8') : $nome;
    }

    private function trecho(string $texto, int $limite = 40): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        return mb_strlen($texto, 'UTF-8') > $limite ? mb_substr($texto, 0, $limite, 'UTF-8') . '…' : $texto;
    }

    private function exigirAdmin(bool $admin): void
    {
        if (!$admin) {
            throw new SimuladoApiException('Apenas administradores podem realizar esta ação.', 403);
        }
    }
}
