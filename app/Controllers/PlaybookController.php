<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Playbook;
use App\Models\PlaybookQuestion;
use App\Models\PlaybookAssignment;
use App\Models\PlaybookAnswer;
use App\Models\User;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\AdminConfig;
use App\Services\OpenAIService;
use App\Services\DocumentParser;

/**
 * PlaybookController - Gerenciamento de Playbooks/Treinamentos
 */
class PlaybookController extends Controller
{
    protected Playbook $playbookModel;
    protected PlaybookQuestion $questionModel;
    protected PlaybookAssignment $assignmentModel;

    public function __construct()
    {
        $this->playbookModel = new Playbook();
        $this->questionModel = new PlaybookQuestion();
        $this->assignmentModel = new PlaybookAssignment();
    }

    /**
     * Listar playbooks da empresa
     */
    public function index(): void
    {
        $user = $this->currentUser();
        $playbooks = $this->playbookModel->getByCompany($user['id']);

        $this->setLayout('dashboard');
        $this->view('playbooks/index', [
            'title' => 'Playbooks',
            'playbooks' => $playbooks,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Página de criar playbook
     */
    public function create(): void
    {
        $this->setLayout('dashboard');
        $this->view('playbooks/create', [
            'title' => 'Criar Playbook',
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Gerar playbook com IA
     */
    public function generate(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        @set_time_limit(120);

        $user = $this->currentUser();
        $title = trim($this->input('title'));
        $sourceType = $this->input('source_type', 'text');
        $content = trim($this->input('content'));
        $sourceFile = '';

        if ($sourceType === 'file' && isset($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = ROOT_PATH . '/public/uploads';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
            $orig = $_FILES['file']['name'] ?? '';
            $tmp = $_FILES['file']['tmp_name'] ?? '';
            $name = uniqid('play_', true) . '_' . preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $orig);
            $dest = $uploadDir . '/' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $parsed = DocumentParser::parse($dest, $orig);
                $content = trim($parsed);
                $sourceFile = 'uploads/' . $name;
            }
            if (empty($content)) {
                $this->json(['error' => 'Não foi possível ler o arquivo enviado. Tente enviar um TXT ou DOCX. Para PDF/DOC, é necessário ter pdftotext/antiword instalados no servidor.'], 400);
            }
        }

        if ($sourceType === 'audio' && isset($_FILES['audio']) && ($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = ROOT_PATH . '/public/uploads';
            if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
            $orig = $_FILES['audio']['name'] ?? 'audio.webm';
            $tmp = $_FILES['audio']['tmp_name'] ?? '';
            $name = uniqid('rec_', true) . '_' . preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $orig);
            $dest = $uploadDir . '/' . $name;
            if (move_uploaded_file($tmp, $dest)) {
                $aiService = new OpenAIService();
                $text = $aiService->transcribeAudio($dest, (int)$user['id']);
                if (!empty($text)) {
                    $content = trim($text);
                    $sourceFile = 'uploads/' . $name;
                }
            }
            if (empty($content)) {
                $this->json(['error' => 'Falha ao transcrever o áudio. Tente novamente.'], 400);
            }
        }

        if (empty($title) || empty($content)) {
            $this->json(['error' => 'Preencha todos os campos'], 400);
        }

        try {
            // Gerar conteúdo com IA
            $aiService = new OpenAIService();
            
            $prompt = "Crie um playbook/treinamento corporativo completo sobre o seguinte tema:\n\n";
            $prompt .= "Título: {$title}\n\n";
            $prompt .= "Conteúdo base:\n{$content}\n\n";
            $prompt .= "O playbook deve conter:\n";
            $prompt .= "1. Introdução\n";
            $prompt .= "2. Objetivos de aprendizagem\n";
            $prompt .= "3. Conteúdo dividido em seções curtas (2–3 parágrafos cada)\n";
            $prompt .= "4. Regras e políticas aplicáveis\n";
            $prompt .= "5. Boas práticas\n";
            $prompt .= "6. Conclusão\n\n";
            $prompt .= "Formate em HTML conciso e bem estruturado com tags h2, h3, p, ul, li, etc.\n";
            $prompt .= "Limite total do conteúdo entre 800 e 1000 palavras.\n";
            $prompt .= "Regras de formatação:\n";
            $prompt .= "- Use exclusivamente tags HTML, sem Markdown.\n";
            $prompt .= "- Não use <h1>; use <h2> para seções e <h3> para subseções.\n";
            $prompt .= "- Cada parágrafo deve estar dentro de <p> com no máximo 2–3 frases.\n";
            $prompt .= "- Use <ul>/<li> para objetivos, regras e boas práticas.\n";
            $prompt .= "- Não inclua <html>, <head> ou <body>.\n";
            $prompt .= "- Não inclua explicações fora do conteúdo.\n";
            $prompt .= "Estrutura esperada (exemplo de marcação):\n";
            $prompt .= "<h2>Introdução</h2><p>...</p><p>...</p>\n";
            $prompt .= "<h2>Objetivos de aprendizagem</h2><ul><li>...</li></ul>\n";
            $prompt .= "<h2>Conteúdo</h2>\n";
            $prompt .= "<h3>Seção 1: ...</h3><p>...</p><ul><li>...</li></ul>\n";
            $prompt .= "<h3>Seção 2: ...</h3><p>...</p>\n";
            $prompt .= "<h3>Seção 3: ...</h3><p>...</p>\n";
            $prompt .= "<h2>Regras e políticas aplicáveis</h2><ul><li>...</li></ul>\n";
            $prompt .= "<h2>Boas práticas</h2><ul><li>...</li></ul>\n";
            $prompt .= "<h2>Conclusão</h2><p>...</p>";

            $contentHtml = $aiService->generateContent($prompt, $user['id']);
            $contentHtml = $this->normalizePlaybookHtml($contentHtml);

            // Gerar questionário
            $questionsPrompt = "Com base no seguinte conteúdo de treinamento, crie 10 perguntas de múltipla escolha (4 alternativas cada: A, B, C, D) para avaliar o aprendizado.\n\n";
            $questionsPrompt .= "Conteúdo:\n{$contentHtml}\n\n";
            $questionsPrompt .= "Retorne as perguntas em formato JSON:\n";
            $questionsPrompt .= '[{"question_text":"pergunta","option_a":"opção A","option_b":"opção B","option_c":"opção C","option_d":"opção D","correct_option":"A","explanation":"explicação da resposta correta"}]';

            $questionsJson = $aiService->generateContent($questionsPrompt, $user['id']);
            
            // Extrair JSON das questões
            preg_match('/\[[\s\S]*\]/', $questionsJson, $matches);
            $questions = json_decode($matches[0] ?? '[]', true);

            // Salvar playbook
            $playbookId = $this->playbookModel->create([
                'company_id' => $user['id'],
                'title' => $title,
                'content_html' => $contentHtml,
                'source_type' => $sourceType,
                'source_file' => $sourceFile,
                'status' => 'draft',
            ]);

            // Salvar questões
            if ($playbookId && !empty($questions)) {
                $this->questionModel->createBatch($playbookId, $questions);
            }

            $this->json([
                'success' => true,
                'playbook_id' => $playbookId,
                'message' => 'Playbook gerado com sucesso!',
            ]);

        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao gerar playbook: ' . $e->getMessage()], 500);
        }
    }

    public function transcribe(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $user = $this->currentUser();
        if (!isset($_FILES['audio']) || ($_FILES['audio']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $this->json(['error' => 'Áudio não enviado'], 400);
        }
        $uploadDir = ROOT_PATH . '/public/uploads';
        if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0755, true); }
        $orig = $_FILES['audio']['name'] ?? 'audio.webm';
        $tmp = $_FILES['audio']['tmp_name'] ?? '';
        $name = uniqid('rec_', true) . '_' . preg_replace('/[^a-zA-Z0-9_\.\-]/', '_', $orig);
        $dest = $uploadDir . '/' . $name;
        if (!move_uploaded_file($tmp, $dest)) {
            $this->json(['error' => 'Falha ao salvar áudio'], 500);
        }
        try {
            $aiService = new OpenAIService();
            $text = $aiService->transcribeAudio($dest, (int)$user['id']);
            $this->json(['success' => true, 'text' => $text, 'file' => 'uploads/' . $name]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Visualizar playbook
     */
    public function show(int $id): void
    {
        $user = $this->currentUser();
        $playbook = $this->playbookModel->getWithQuestions($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->flash('error', 'Playbook não encontrado.');
            $this->redirect('playbooks');
        }

        // Buscar atribuições
        $assignments = $this->assignmentModel->getByPlaybook($id);

        $this->setLayout('dashboard');
        $this->view('playbooks/show', [
            'title' => $playbook['title'],
            'playbook' => $playbook,
            'assignments' => $assignments,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Publicar playbook
     */
    public function publish(int $id): void
    {
        $user = $this->currentUser();
        $playbook = $this->playbookModel->find($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->json(['error' => 'Playbook não encontrado'], 404);
        }

        // Verificar franquia do plano antes de cobrar taxa
        $config = new AdminConfig();
        $playbookFee = (float) $config->get('playbook_fee', 19.90);

        $subscriptionModel = new Subscription();
        $sub = $subscriptionModel->getActiveByCompany((int)$user['id']);
        $planMax = isset($sub['plan_max_playbooks']) ? (int)$sub['plan_max_playbooks'] : 0; // 0/null = ilimitado

        // Determinar período de cobrança
        $startDate = $sub['current_period_start'] ?? null;
        $endDate = $sub['current_period_end'] ?? null;
        if (!$startDate || !$endDate) {
            // fallback: mês atual
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }
        $startTs = $startDate . ' 00:00:00';
        $endTs = $endDate . ' 23:59:59';

        // Contabilizar playbooks publicados no período
        $used = $this->playbookModel->countPublishedInPeriod((int)$user['id'], $startTs, $endTs);

        $hasAllowance = ($planMax === 0 || $planMax === null) ? true : ($used < $planMax);

        if (!$hasAllowance && !$playbook['is_paid'] && $playbookFee > 0) {
            $this->json([
                'success' => false,
                'requires_payment' => true,
                'amount' => $playbookFee,
                'used' => $used,
                'limit' => $planMax,
                'message' => 'Limite do plano atingido no período. É necessário pagamento avulso para publicar este playbook.',
            ]);
            return;
        }

        $this->playbookModel->update($id, ['status' => 'published']);

        $this->json([
            'success' => true,
            'message' => 'Playbook publicado com sucesso!',
        ]);
    }

    /**
     * Reformatar/normalizar conteúdo HTML do playbook
     */
    public function reformat(int $id): void
    {
        $user = $this->currentUser();
        $playbook = $this->playbookModel->find($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->json(['error' => 'Playbook não encontrado'], 404);
        }

        $html = (string)($playbook['content_html'] ?? '');
        $normalized = $this->normalizePlaybookHtml($html);

        if ($normalized !== $html) {
            $this->playbookModel->update($id, ['content_html' => $normalized]);
        }

        $this->json(['success' => true, 'message' => 'Conteúdo reformatado com sucesso!']);
    }

    /**
     * Página de atribuir playbook
     */
    public function assignPage(int $id): void
    {
        $user = $this->currentUser();
        $playbook = $this->playbookModel->find($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->flash('error', 'Playbook não encontrado.');
            $this->redirect('playbooks');
        }

        // Buscar funcionários
        $userModel = new User();
        $employees = $userModel->getEmployeesByCompany($user['id']);

        // Buscar já atribuídos
        $assignments = $this->assignmentModel->getByPlaybook($id);
        $assignedIds = array_column($assignments, 'employee_id');

        $this->setLayout('dashboard');
        $this->view('playbooks/assign', [
            'title' => 'Atribuir Playbook',
            'playbook' => $playbook,
            'employees' => $employees,
            'assignedIds' => $assignedIds,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Atribuir playbook a funcionários
     */
    public function assign(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();
        $playbook = $this->playbookModel->find($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->json(['error' => 'Playbook não encontrado'], 404);
        }

        $employeeIds = $this->input('employee_ids', []);
        $dueDate = $this->input('due_date');

        if (empty($employeeIds)) {
            $this->json(['error' => 'Selecione pelo menos um funcionário'], 400);
        }

        $assigned = 0;
        foreach ($employeeIds as $employeeId) {
            if (!$this->assignmentModel->isAssigned($id, $employeeId)) {
                $this->assignmentModel->create([
                    'playbook_id' => $id,
                    'employee_id' => $employeeId,
                    'assigned_by' => $user['id'],
                    'due_date' => $dueDate ?: null,
                ]);
                $assigned++;
            }
        }

        $this->json([
            'success' => true,
            'message' => "{$assigned} funcionário(s) atribuído(s) com sucesso!",
        ]);
    }

    /**
     * Normaliza HTML do playbook para garantir hierarquia (h2/h3), parágrafos curtos e listas
     */
    private function normalizePlaybookHtml(string $html): string
    {
        $text = trim($html);
        if ($text === '') return '';

        // Se veio sem tags HTML relevantes, converter de texto puro para HTML básico
        $hasBlockTags = preg_match('/<(h[1-6]|p|ul|ol|li|div|section)\b/i', $text) === 1;
        if (!$hasBlockTags) {
            $lines = preg_split('/\r\n|\r|\n/', $text);
            $out = '';
            $openUl = false;
            foreach ($lines as $raw) {
                $line = trim($raw);
                if ($line === '') {
                    if ($openUl) { $out .= '</ul>'; $openUl = false; }
                    continue;
                }
                // Títulos principais
                if (preg_match('/^(Introdução|Objetivos de aprendizagem|Conteúdo|Regras e políticas aplicáveis|Boas práticas|Conclusão)\s*:?/i', $line)) {
                    if ($openUl) { $out .= '</ul>'; $openUl = false; }
                    $out .= '<h2>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</h2>';
                    continue;
                }
                // Subtítulos de seção
                if (preg_match('/^Seção\s*\d+\s*:\s*(.+)$/i', $line, $m)) {
                    if ($openUl) { $out .= '</ul>'; $openUl = false; }
                    $out .= '<h3>Seção ' . htmlspecialchars($m[0], ENT_QUOTES, 'UTF-8') . '</h3>';
                    continue;
                }
                // Itens de lista
                if (preg_match('/^([\-•\*])\s+(.+)$/u', $line, $m)) {
                    if (!$openUl) { $out .= '<ul>'; $openUl = true; }
                    $out .= '<li>' . htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8') . '</li>';
                    continue;
                }
                // Parágrafo padrão
                $out .= '<p>' . htmlspecialchars($line, ENT_QUOTES, 'UTF-8') . '</p>';
            }
            if ($openUl) { $out .= '</ul>'; }
            $text = $out;
        }

        // Normalizações em HTML existente
        // h1 -> h2
        $text = preg_replace('/<\s*h1\b([^>]*)>/i', '<h2$1>', $text);
        $text = preg_replace('/<\s*\/\s*h1\s*>/i', '</h2>', $text);

        // Envolver linhas soltas sem tag em <p>
        $parts = preg_split('/(\r\n|\r|\n)/', $text);
        if ($parts && count($parts) > 1) {
            $rebuilt = '';
            foreach ($parts as $ln) {
                $trim = trim($ln);
                if ($trim === '') { continue; }
                if ($trim[0] !== '<') {
                    // linha de texto solta
                    $rebuilt .= '<p>' . htmlspecialchars($trim, ENT_QUOTES, 'UTF-8') . '</p>';
                } else {
                    $rebuilt .= $ln;
                }
            }
            if ($rebuilt !== '') $text = $rebuilt;
        }

        return $text;
    }

    /**
     * Deletar playbook
     */
    public function delete(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $user = $this->currentUser();
        $playbook = $this->playbookModel->find($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->json(['error' => 'Playbook não encontrado'], 404);
        }

        try {
            // Apagar dependências
            $this->questionModel->deleteByPlaybook($id);
            $this->assignmentModel->deleteByPlaybook($id);
            // Apagar playbook
            $this->playbookModel->delete($id);
            $this->json(['success' => true, 'message' => 'Playbook excluído com sucesso!']);
        } catch (\Exception $e) {
            $this->json(['error' => 'Falha ao excluir playbook: ' . $e->getMessage()], 500);
        }
    }
}
