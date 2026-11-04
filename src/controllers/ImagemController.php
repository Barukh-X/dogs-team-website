<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/Imagem.php';
require_once __DIR__ . '/../services/UploadImagemService.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/auth_admin_check.php';
require_once __DIR__ . '/../includes/helpers.php';

class ImagemController
{
    private PDO $pdo;
    private Imagem $modelo;
    private UploadImagemService $upload;

    public function __construct()
    {
        exigirAutenticacao();
        $this->pdo = ConectarDB();
        $this->modelo = new Imagem($this->pdo);
        $this->upload = new UploadImagemService();
    }

    private function usuarioId(): int
    {
        return (int) $GLOBALS['auth_user']['id'];
    }

    private function ehAdmin(): bool
    {
        return !empty($GLOBALS['auth_user']['isadmin']);
    }

    private function urlImagem(int $id): string
    {
        $diretorioAplicacao = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $prefixo = rtrim($diretorioAplicacao, '/');
        if ($prefixo === '.' || $prefixo === '/') $prefixo = '';
        return $prefixo . '/api/imagens/arquivo?id=' . $id;
    }

    private function tipoValido(mixed $tipo): ?string
    {
        return is_string($tipo) && in_array($tipo, ['anotacao', 'data_importante', 'perfil', 'questao', 'resposta'], true)
            ? $tipo : null;
    }

    /** Permissão do usuário sobre o recurso ao qual a imagem pertence. */
    private function podeAcessarRecurso(string $tipo, int $recursoId, bool $escrita): bool
    {
        $recurso = $this->modelo->buscarRecurso($tipo, $recursoId);
        if ($recurso === null) {
            return false;
        }

        $uid = $this->usuarioId();
        $admin = $this->ehAdmin();

        return match ($tipo) {
            'anotacao' => $escrita
                ? ((int) $recurso['id_usuario'] === $uid || ($admin && (bool) $recurso['publica']))
                : ((int) $recurso['id_usuario'] === $uid || (bool) $recurso['publica']),
            'data_importante' => !$escrita || $admin,
            // Leitura: o próprio dono, administradores ou qualquer usuário quando o dono está no ranking.
            'perfil' => $escrita
                ? ((int) $recurso['id_usuario'] === $uid)
                : ((int) $recurso['id_usuario'] === $uid || $admin
                    || $this->modelo->perfilVisivelNoRanking((int) $recurso['id_usuario'])),
            // Somente administradores anexam/removem imagens de questões (e apenas em rascunhos).
            // Os demais usuários só veem as imagens de um simulado publicado depois de iniciá-lo.
            'questao' => $escrita
                ? ($recurso['status_simulado'] === 'rascunho' && $admin)
                : ($admin || ($recurso['status_simulado'] === 'publicado'
                    && $this->modelo->usuarioTemTentativa($uid, (int) $recurso['id_simulado']))),
            // Fotos da resposta: o dono as envia/remove enquanto a tentativa está em andamento e o tempo não
            // acabou (depois disso ficam fixas para a correção). Leitura: o dono e administradores.
            'resposta' => $escrita
                ? ((int) $recurso['id_usuario'] === $uid
                    && $recurso['status_tentativa'] === 'em_andamento'
                    && !(bool) $recurso['expirada']
                    && in_array($recurso['tipo_questao'], ['discursiva', 'objetiva_justificativa'], true))
                : ((int) $recurso['id_usuario'] === $uid || $admin),
            default => false,
        };
    }

    private function alvoDaImagem(array $imagem): ?array
    {
        $alvos = [
            'anotacao' => 'id_anotacao',
            'data_importante' => 'id_data_importante',
            'perfil' => 'id_usuario_perfil',
            'questao' => 'id_questao',
            'resposta' => 'id_resposta',
        ];
        $encontrados = [];
        foreach ($alvos as $tipo => $coluna) {
            if (isset($imagem[$coluna])) {
                $encontrados[] = ['tipo' => $tipo, 'id' => (int) $imagem[$coluna]];
            }
        }
        return count($encontrados) === 1 ? $encontrados[0] : null;
    }

    private function podeAcessarImagem(array $imagem, bool $escrita): bool
    {
        $alvo = $this->alvoDaImagem($imagem);
        return $alvo !== null && $this->podeAcessarRecurso($alvo['tipo'], $alvo['id'], $escrita);
    }

    public function listar(): void
    {
        $tipo = $this->tipoValido($_GET['tipo'] ?? null);
        if ($tipo === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'tipo deve ser anotacao, data_importante, perfil, questao ou resposta']);
            return;
        }

        if ($tipo === 'perfil' && !isset($_GET['id_recurso'])) {
            $recursoId = $this->usuarioId();
        } else {
            $recursoId = idValido($_GET['id_recurso'] ?? null);
        }

        if ($recursoId === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_recurso inválido ou obrigatório']);
            return;
        }
        if (!$this->podeAcessarRecurso($tipo, $recursoId, false)) {
            responderJson(404, ['sucesso' => false, 'erro' => 'Recurso não encontrado ou sem permissão de acesso']);
            return;
        }

        $imagens = $this->modelo->listarPorRecurso($tipo, $recursoId);
        foreach ($imagens as &$imagem) {
            $imagem['url'] = $this->urlImagem((int) $imagem['id']);
        }
        unset($imagem);
        responderJson(200, ['sucesso' => true, 'imagens' => $imagens]);
    }

    public function enviar(): void
    {
        $tipo = $this->tipoValido($_POST['tipo'] ?? null);
        if ($tipo === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'Envie tipo: anotacao, data_importante, perfil, questao ou resposta']);
            return;
        }

        if ($tipo === 'perfil') {
            $recursoId = $this->usuarioId();
        } else {
            $recursoId = idValido($_POST['id_recurso'] ?? null);
        }

        if ($recursoId === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'id_recurso obrigatório e inválido']);
            return;
        }
        if (!$this->podeAcessarRecurso($tipo, $recursoId, true)) {
            responderJson(403, ['sucesso' => false, 'erro' => 'Você não tem permissão para anexar imagens a esse recurso']);
            return;
        }

        try {
            $arquivos = $this->upload->normalizarArquivos($_FILES);
            if ($tipo === 'resposta') {
                $limite = UploadImagemService::MAX_IMAGENS_POR_RESPOSTA;
                $existentes = $this->modelo->contarPorRecurso('resposta', $recursoId);
                if ($existentes + count($arquivos) > $limite) {
                    throw new InvalidArgumentException("Cada resposta aceita no máximo {$limite} fotos (já há {$existentes}).");
                }
            }
            $ids = $this->upload->salvarLote($arquivos, $tipo, $recursoId, $this->usuarioId(), $this->modelo);
            $itens = [];
            foreach ($ids as $id) {
                $imagem = $this->modelo->buscarPorId($id);
                if ($imagem !== null) {
                    $itens[] = [
                        'id' => (int) $imagem['id'],
                        'nome_original' => $imagem['nome_original'],
                        'tipo_mime' => $imagem['tipo_mime'],
                        'tamanho' => (int) $imagem['tamanho'],
                        'url' => $this->urlImagem((int) $imagem['id']),
                    ];
                }
            }
            responderJson(201, ['sucesso' => true, 'mensagem' => 'Upload concluído', 'imagens' => $itens]);
        } catch (InvalidArgumentException $e) {
            responderJson(400, ['sucesso' => false, 'erro' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('Dogs Team - falha no upload de imagem: ' . $e->getMessage());
            responderJson(500, ['sucesso' => false, 'erro' => 'Não foi possível armazenar as imagens. Verifique os limites e as permissões da hospedagem.']);
        }
    }

    /** Entrega o conteúdo do arquivo somente após autenticação e autorização. */
    public function arquivo(): void
    {
        $id = idValido($_GET['id'] ?? null);
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'ID da imagem inválido']);
            return;
        }

        $imagem = $this->modelo->buscarPorId($id);
        if ($imagem === null || !$this->podeAcessarImagem($imagem, false)) {
            responderJson(404, ['sucesso' => false, 'erro' => 'Imagem não encontrada']);
            return;
        }

        $caminho = $this->upload->caminhoSeguro((string) $imagem['arquivo']);
        if ($caminho === null) {
            responderJson(404, ['sucesso' => false, 'erro' => 'Arquivo de imagem não encontrado']);
            return;
        }

        $mimePermitido = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime = in_array($imagem['tipo_mime'], $mimePermitido, true) ? $imagem['tipo_mime'] : 'application/octet-stream';
        // O middleware global define JSON para os endpoints da API; remova-o antes do binário.
        // Isso também evita cabeçalhos Content-Type duplicados em alguns ambientes de hospedagem.
        header_remove('Content-Type');
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($caminho));
        header('Content-Disposition: inline; filename="imagem-' . $id . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store, max-age=0');
        header('Vary: Authorization');
        readfile($caminho);
    }

    public function excluir(): void
    {
        $dados = lerJson();
        $id = is_array($dados) ? idValido($dados['id'] ?? null) : null;
        if ($id === null) {
            responderJson(400, ['sucesso' => false, 'erro' => 'ID da imagem inválido']);
            return;
        }

        $imagem = $this->modelo->buscarPorId($id);
        if ($imagem === null) {
            responderJson(404, ['sucesso' => false, 'erro' => 'Imagem não encontrada']);
            return;
        }
        if (!$this->podeAcessarImagem($imagem, true)) {
            responderJson(403, ['sucesso' => false, 'erro' => 'Você não tem permissão para excluir esta imagem']);
            return;
        }

        if ($this->modelo->excluir($id)) {
            $this->upload->removerArquivoFisico((string) $imagem['arquivo']);
            responderJson(200, ['sucesso' => true, 'mensagem' => 'Imagem excluída']);
            return;
        }
        responderJson(500, ['sucesso' => false, 'erro' => 'Não foi possível excluir a imagem']);
    }
}
