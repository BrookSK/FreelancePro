<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Playbook;
use App\Models\PlaybookQuestion;
use App\Models\PlaybookAssignment;
use App\Models\PlaybookAnswer;
use App\Models\User;
use App\Models\Payment;
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
            $prompt .= "Limite total do conteúdo entre 800 e 1000 palavras.";

            $contentHtml = $aiService->generateContent($prompt, $user['id']);

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

        // Verificar se precisa pagar
        $config = new AdminConfig();
        $playbookFee = $config->get('playbook_fee', 19.90);

        if (!$playbook['is_paid'] && $playbookFee > 0) {
            $this->json([
                'success' => false,
                'requires_payment' => true,
                'amount' => $playbookFee,
                'message' => 'Este playbook requer pagamento para publicação.',
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
     * Deletar playbook
     */
    public function delete(int $id): void
    {
        $user = $this->currentUser();
        $playbook = $this->playbookModel->find($id);

        if (!$playbook || $playbook['company_id'] != $user['id']) {
            $this->json(['error' => 'Playbook não encontrado'], 404);
        }

        $this->playbookModel->delete($id);

        $this->json([
            'success' => true,
            'message' => 'Playbook excluído com sucesso!',
        ]);
    }
}
