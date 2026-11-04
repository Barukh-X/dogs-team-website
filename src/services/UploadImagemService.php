<?php

require_once __DIR__ . '/../models/Imagem.php';

/** Valida e armazena imagens fora do diretório público do site. */
class UploadImagemService
{
    public const MAX_ARQUIVO_BYTES = 5242880; // 5 MiB por imagem
    public const MAX_ARQUIVOS_POR_REQUISICAO = 5;
    public const MAX_IMAGENS_POR_RESPOSTA = 5;

    private string $diretorio;

    private const TIPOS_PERMITIDOS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(?string $diretorio = null)
    {
        $this->diretorio = $diretorio ?? dirname(__DIR__, 2) . '/storage/uploads';
    }

    /** Aceita tanto o campo multipart "imagem" quanto "imagens[]". */
    public function normalizarArquivos(array $files): array
    {
        $entrada = $files['imagens'] ?? $files['imagem'] ?? null;
        if (!is_array($entrada) || !array_key_exists('name', $entrada)) {
            throw new InvalidArgumentException('Envie o arquivo no campo imagem ou imagens[].');
        }

        $arquivos = [];
        if (is_array($entrada['name'])) {
            $quantidade = count($entrada['name']);
            for ($i = 0; $i < $quantidade; $i++) {
                $arquivo = [
                    'name' => $entrada['name'][$i] ?? '',
                    'type' => $entrada['type'][$i] ?? '',
                    'tmp_name' => $entrada['tmp_name'][$i] ?? '',
                    'error' => $entrada['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $entrada['size'][$i] ?? 0,
                ];
                if ((int) $arquivo['error'] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }
                $arquivos[] = $arquivo;
            }
        } else {
            if ((int) ($entrada['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $arquivos[] = $entrada;
            }
        }

        if ($arquivos === []) {
            throw new InvalidArgumentException('Nenhuma imagem foi enviada.');
        }
        if (count($arquivos) > self::MAX_ARQUIVOS_POR_REQUISICAO) {
            throw new InvalidArgumentException('Envie no máximo 5 imagens por requisição.');
        }

        return array_map(fn(array $arquivo): array => $this->validarArquivo($arquivo), $arquivos);
    }

    private function validarArquivo(array $arquivo): array
    {
        $erro = (int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($erro !== UPLOAD_ERR_OK) {
            $mensagem = match ($erro) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A imagem excede o limite de upload do servidor.',
                UPLOAD_ERR_PARTIAL => 'O envio da imagem foi interrompido.',
                UPLOAD_ERR_NO_TMP_DIR => 'Diretório temporário de upload indisponível.',
                UPLOAD_ERR_CANT_WRITE => 'O servidor não conseguiu gravar o arquivo temporário.',
                UPLOAD_ERR_EXTENSION => 'Uma extensão do PHP bloqueou o upload.',
                default => 'Falha no envio da imagem.',
            };
            throw new InvalidArgumentException($mensagem);
        }

        $tmp = $arquivo['tmp_name'] ?? '';
        $tamanho = (int) ($arquivo['size'] ?? 0);
        if (!is_string($tmp) || $tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Arquivo enviado inválido.');
        }
        if ($tamanho < 1 || $tamanho > self::MAX_ARQUIVO_BYTES) {
            throw new InvalidArgumentException('Cada imagem deve ter tamanho entre 1 byte e 5 MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);
        $info = @getimagesize($tmp);
        if (
            !is_string($mime)
            || !isset(self::TIPOS_PERMITIDOS[$mime])
            || !is_array($info)
            || !isset($info['mime'])
            || $info['mime'] !== $mime
        ) {
            throw new InvalidArgumentException('Formato inválido. Use JPEG, PNG, WebP ou GIF.');
        }
        $largura = (int) ($info[0] ?? 0);
        $altura = (int) ($info[1] ?? 0);
        if ($largura < 1 || $altura < 1 || $largura > 20000 || $altura > 20000 || ($largura * $altura) > 40000000) {
            throw new InvalidArgumentException('As dimensões da imagem excedem o limite permitido.');
        }

        $nome = str_replace(["\r", "\n", "\0"], '', basename((string) ($arquivo['name'] ?? 'imagem')));
        $nome = trim($nome);
        if ($nome === '') {
            $nome = 'imagem.' . self::TIPOS_PERMITIDOS[$mime];
        }
        if (function_exists('mb_substr')) {
            $nome = mb_substr($nome, 0, 255, 'UTF-8');
        } else {
            $nome = substr($nome, 0, 255);
        }

        return [
            'tmp_name' => $tmp,
            'nome_original' => $nome,
            'tipo_mime' => $mime,
            'extensao' => self::TIPOS_PERMITIDOS[$mime],
            'tamanho' => $tamanho,
        ];
    }

    public function salvarLote(array $arquivos, string $tipo, int $recursoId, int $usuarioId, Imagem $modelo): array
    {
        if ($tipo === 'perfil' && count($arquivos) !== 1) {
            throw new InvalidArgumentException('Envie exatamente uma imagem para a foto de perfil.');
        }

        if (!is_dir($this->diretorio) && !@mkdir($this->diretorio, 0750, true) && !is_dir($this->diretorio)) {
            throw new RuntimeException('Não foi possível preparar o armazenamento de imagens.');
        }
        if (!is_writable($this->diretorio)) {
            throw new RuntimeException('O armazenamento de imagens não permite gravação.');
        }

        $pdo = $modelo->obterPdo();
        $novosArquivos = [];
        $arquivosAntigos = [];
        $ids = [];

        if ($tipo === 'perfil') {
            $antigo = $modelo->buscarPerfil($recursoId);
            if ($antigo !== null) {
                $arquivosAntigos[] = $antigo['arquivo'];
            }
        }

        $pdo->beginTransaction();
        try {
            foreach ($arquivos as $arquivo) {
                $nomeFisico = bin2hex(random_bytes(20)) . '.' . $arquivo['extensao'];
                $destino = $this->diretorio . DIRECTORY_SEPARATOR . $nomeFisico;

                if (!move_uploaded_file($arquivo['tmp_name'], $destino)) {
                    throw new RuntimeException('Não foi possível armazenar uma das imagens.');
                }
                @chmod($destino, 0640);
                $novosArquivos[] = $nomeFisico;

                $ids[] = $modelo->registrar(
                    $tipo,
                    $recursoId,
                    $usuarioId,
                    $nomeFisico,
                    $arquivo['nome_original'],
                    $arquivo['tipo_mime'],
                    $arquivo['tamanho']
                );
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            foreach ($novosArquivos as $nomeFisico) {
                $this->removerArquivoFisico($nomeFisico);
            }
            throw $e;
        }

        foreach ($arquivosAntigos as $nomeAntigo) {
            $this->removerArquivoFisico((string) $nomeAntigo);
        }

        return $ids;
    }

    public function caminhoSeguro(string $nomeFisico): ?string
    {
        if ($nomeFisico === '' || basename($nomeFisico) !== $nomeFisico || str_contains($nomeFisico, '..')) {
            return null;
        }

        $caminho = $this->diretorio . DIRECTORY_SEPARATOR . $nomeFisico;
        return is_file($caminho) ? $caminho : null;
    }

    public function removerArquivoFisico(string $nomeFisico): void
    {
        $caminho = $this->caminhoSeguro($nomeFisico);
        if ($caminho !== null) {
            @unlink($caminho);
        }
    }

    public function removerLista(array $arquivos): void
    {
        foreach ($arquivos as $arquivo) {
            if (is_array($arquivo) && isset($arquivo['arquivo'])) {
                $this->removerArquivoFisico((string) $arquivo['arquivo']);
            }
        }
    }
}
