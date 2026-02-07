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

        $user = $this->currentUser();
        $title = trim($this->input('title'));
        $sourceType = $this->input('source_type', 'text');
        $content = trim($this->input('content'));

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
            $prompt .= "3. Conteúdo detalhado dividido em seções\n";
            $prompt .= "4. Regras e políticas aplicáveis\n";
            $prompt .= "5. Boas práticas\n";
            $prompt .= "6. Conclusão\n\n";
            $prompt .= "Formate em HTML bem estruturado com tags h2, h3, p, ul, li, etc.";

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
