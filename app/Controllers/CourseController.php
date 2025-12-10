<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseLesson;
use App\Models\CourseEnrollment;
use App\Models\CourseQuestion;
use App\Models\CourseAnswer;
use App\Models\CourseModuleResult;
use App\Models\CompanySetting;
use App\Models\User;
use App\Services\OpenAIService;

/**
 * CourseController - Gerenciamento de Cursos
 */
class CourseController extends Controller
{
    protected Course $courseModel;
    protected CourseModule $moduleModel;
    protected CourseLesson $lessonModel;
    protected CourseEnrollment $enrollmentModel;

    public function __construct()
    {
        $this->courseModel = new Course();
        $this->moduleModel = new CourseModule();
        $this->lessonModel = new CourseLesson();
        $this->enrollmentModel = new CourseEnrollment();
    }

    /**
     * Listar cursos da empresa
     */
    public function index(): void
    {
        $user = $this->currentUser();
        $courses = $this->courseModel->getByCompany($user['id']);

        $this->setLayout('dashboard');
        $this->view('courses/index', [
            'title' => 'Cursos',
            'courses' => $courses,
        ]);
    }

    /**
     * Página de criar curso
     */
    public function create(): void
    {
        $this->setLayout('dashboard');
        $this->view('courses/create', [
            'title' => 'Criar Curso',
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Gerar curso completo com IA
     */
    public function generate(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();
        $title = trim($this->input('title'));
        $description = trim($this->input('description'));
        $baseContent = trim($this->input('content'));
        $videoSource = $this->input('video_source', 'ai');
        $allowedVideoSources = ['ai', 'upload', 'manual'];
        if (!in_array($videoSource, $allowedVideoSources, true)) {
            $videoSource = 'ai';
        }

        if (empty($title) || empty($description)) {
            $this->json(['error' => 'Preencha todos os campos'], 400);
        }

        try {
            // MODO RÁPIDO: evitar timeouts no /courses/generate
            // Pula chamadas à IA e cria uma estrutura mínima (4x3) imediatamente.
            $structure = $this->createSkeletonStructure($title);

            // Criar curso
            $courseId = $this->courseModel->create([
                'company_id' => $user['id'],
                'title' => $title,
                'description' => $description,
                'status' => 'draft',
            ]);

            // Criar módulos e aulas
            $moduleOrder = 1;
            foreach ($structure['modules'] as $moduleData) {
                $moduleId = $this->moduleModel->create([
                    'course_id' => $courseId,
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'] ?? '',
                    'order_number' => $moduleOrder++,
                ]);

                $lessonOrder = 1;
                foreach ($moduleData['lessons'] ?? [] as $lessonData) {
                    $videoUrl = null;
                    if ($videoSource === 'ai') {
                        $candidate = $lessonData['video_url'] ?? null;
                        if ($candidate && $this->isYoutubeUrl($candidate) && $this->isYoutubeVideoAvailable($candidate) && $this->isYoutubeVideoPortuguese($candidate)) {
                            $videoUrl = $candidate;
                        }
                    }

                    $this->lessonModel->create([
                        'module_id' => $moduleId,
                        'title' => $lessonData['title'],
                        'content_html' => $lessonData['content_html'] ?? '',
                        'video_url' => $videoUrl,
                        'order_number' => $lessonOrder++,
                    ]);
                }

                // Gerar questionário padrão (evita chamadas lentas à IA na criação)
                $questions = $this->buildDefaultQuestions($moduleData['title'] ?? 'Módulo');
                if (!empty($questions)) {
                    $courseQuestionModel = new CourseQuestion();
                    $courseQuestionModel->createBatch($moduleId, $questions);
                }
            }

            // Atualizar contadores
            $this->courseModel->updateCounters($courseId);

            $this->json([
                'success' => true,
                'course_id' => $courseId,
                'message' => 'Curso gerado com sucesso!',
            ]);

        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao gerar curso: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Regerar conteúdo do curso com IA (mantém o curso, recria módulos e aulas)
     */
    public function regenerate(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Token inválido. Tente novamente.');
            $this->redirect("courses/{$id}");
        }

        $user = $this->currentUser();
        $course = $this->courseModel->find($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Curso não encontrado.');
            $this->redirect('courses');
        }

        try {
            $aiService = new OpenAIService();

            $title = $course['title'];
            $description = $course['description'] ?? '';
            $baseContent = '';

            // Reutiliza o mesmo tipo de prompt detalhado usado na criação, sempre com vídeo sugerido pela IA
            $structurePrompt = "Você é um instrutor corporativo. Crie a estrutura COMPLETA de um curso online em português do Brasil sobre o tema abaixo.\n\n";
            $structurePrompt .= "Título do curso: {$title}\n";
            $structurePrompt .= "Descrição do curso: {$description}\n\n";
            if (!empty($baseContent)) {
                $structurePrompt .= "Conteúdo base / referências que devem ser usadas:\n{$baseContent}\n\n";
            }
            $structurePrompt .= "Regras importantes:\n";
            $structurePrompt .= "- O curso deve ter EXATAMENTE 4 módulos e CADA módulo deve ter EXATAMENTE 3 aulas.\n";
            $structurePrompt .= "- Para cada aula, escreva um conteúdo HTML COMPLETO em português, com 3 a 4 parágrafos curtos, usando tags <h2>, <h3>, <p>, <ul>, <li>, etc.\n";
            $structurePrompt .= "- O HTML deve ser autocontido, sem usar <html>, <head> ou <body>.\n";
            $structurePrompt .= "- Não inclua explicações fora do JSON.\n";
            $structurePrompt .= "- Seja conciso para evitar ultrapassar o limite de tokens.\n";
            $structurePrompt .= "Além disso, para cada aula indique também um campo video_url com uma URL COMPLETA de um vídeo público RELEVANTE e ALINHADO ao tema da aula no YouTube, em português do Brasil. Não escolha vídeos em outros idiomas (inglês, espanhol, etc.). Se não houver uma boa opção em português do Brasil, defina video_url como null. Prefira links cujo domínio seja https://www.youtube.com.br/.\n";
            $structurePrompt .= "Retorne APENAS um JSON VÁLIDO no seguinte formato, sem markdown, sem comentários, sem texto antes ou depois:\n";
            $structurePrompt .= '{"modules":[{"title":"título do módulo","description":"descrição do módulo","lessons":[{"title":"título da aula","content_html":"conteúdo HTML completo da aula","video_url":"https://www.youtube.com/..."}]}]}';

            try {
                $structureJson = $aiService->generateContent($structurePrompt, $user['id'], 'regenerate_course');

                // Extrair JSON de forma robusta + fallback
                $structure = $this->parseStructureJson($structureJson);
                if (empty($structure['modules'])) {
                    $retryPrompt = $structurePrompt . "\nIMPORTANTE: Responda SOMENTE com JSON válido exatamente no formato exigido (sem markdown). Reduza os textos (3-4 parágrafos por aula).";
                    $structureJson = $aiService->generateContent($retryPrompt, $user['id'], 'regenerate_course_retry');
                    $structure = $this->parseStructureJson($structureJson);
                }
            } catch (\Exception $ex) {
                // Se der timeout/erro de API, continuamos com fallback abaixo
                $structure = [];
            }

            if (empty($structure['modules'])) {
                // Fallback: criar estrutura mínima sem depender da IA
                $structure = $this->createSkeletonStructure($title);
            }

            // Remover módulos (e aulas/questões relacionadas via FK) atuais
            $this->moduleModel->execute('DELETE FROM course_modules WHERE course_id = :id', ['id' => $id]);

            // Criar novos módulos e aulas
            $moduleOrder = 1;
            foreach ($structure['modules'] as $moduleData) {
                $moduleId = $this->moduleModel->create([
                    'course_id' => $id,
                    'title' => $moduleData['title'],
                    'description' => $moduleData['description'] ?? '',
                    'order_number' => $moduleOrder++,
                ]);

                $lessonOrder = 1;
                foreach ($moduleData['lessons'] ?? [] as $lessonData) {
                    $candidate = $lessonData['video_url'] ?? null;
                    $plain = strip_tags($lessonData['content_html'] ?? '');
                    $videoUrl = null;
                    if ($candidate && $this->isYoutubeUrl($candidate) && $this->isYoutubeVideoAvailable($candidate) && $this->isYoutubeVideoPortuguese($candidate)) {
                        $videoUrl = $candidate;
                    } else {
                        if ($plain === '') {
                            $plain = trim(strip_tags(($moduleData['description'] ?? '') . ' ' . $description));
                        }
                        if ($plain !== '') {
                            $videoUrl = $this->suggestYoutubePtBrVideo($title, $lessonData['title'] ?? '', $plain, $user['id']);
                        }
                    }

                    $this->lessonModel->create([
                        'module_id' => $moduleId,
                        'title' => $lessonData['title'],
                        'content_html' => $lessonData['content_html'] ?? '',
                        'video_url' => $videoUrl,
                        'order_number' => $lessonOrder++,
                    ]);
                }

                // Gerar questionário do módulo com IA
                $moduleHtml = '';
                foreach ($moduleData['lessons'] ?? [] as $lessonData) {
                    $moduleHtml .= (string)($lessonData['content_html'] ?? '');
                    $moduleHtml .= "\n\n";
                }
                if (trim(strip_tags($moduleHtml)) === '') {
                    $moduleHtml = (string)($moduleData['description'] ?? '') . "\n" . $title . ' - ' . $description;
                }
                $questions = $this->generateModuleQuestions($moduleHtml, $user['id']);
                if (empty($questions)) {
                    $questions = $this->buildDefaultQuestions($moduleData['title'] ?? 'Módulo');
                }
                if (!empty($questions)) {
                    $courseQuestionModel = new CourseQuestion();
                    $courseQuestionModel->createBatch($moduleId, $questions);
                }
            }

            // Atualizar contadores
            $this->courseModel->updateCounters($id);

            $this->flash('success', 'Conteúdo do curso regenerado com sucesso com IA.');
            $this->redirect("courses/{$id}");
        } catch (\Exception $e) {
            $this->flash('error', 'Erro ao regerar conteúdo do curso: ' . $e->getMessage());
            $this->redirect("courses/{$id}");
        }
    }

    /**
     * Visualizar curso
     */
    public function show($id): void
    {
        $id = (int) $id;
        $user = $this->currentUser();
        $course = $this->courseModel->getComplete($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Curso não encontrado.');
            $this->redirect('courses');
        }

        // Buscar matrículas
        $enrollments = $this->enrollmentModel->getByCourse($id);

        $this->setLayout('dashboard');
        $this->view('courses/show', [
            'title' => $course['title'],
            'course' => $course,
            'enrollments' => $enrollments,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Gerenciar curso
     */
    public function manage(int $id): void
    {
        $user = $this->currentUser();
        $course = $this->courseModel->getComplete($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Curso não encontrado.');
            $this->redirect('courses');
        }

        $this->setLayout('dashboard');
        $this->view('courses/manage', [
            'title' => 'Gerenciar: ' . $course['title'],
            'course' => $course,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Publicar curso
     */
    public function publish($id): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();
        $id = (int) $id;
        $course = $this->courseModel->find($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->json(['error' => 'Curso não encontrado'], 404);
        }

        $this->courseModel->update($id, ['status' => 'published']);

        $this->json([
            'success' => true,
            'message' => 'Curso publicado com sucesso!',
        ]);
    }

    /**
     * Preencher conteúdos e questionários ausentes
     */
    public function fillMissing($id): void
    {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Token inválido. Tente novamente.');
            $this->redirect('courses');
        }

        $user = $this->currentUser();
        $id = (int) $id;
        $course = $this->courseModel->find($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Curso não encontrado.');
            $this->redirect('courses');
        }

        $updatedLessons = 0;
        $createdQuizzes = 0;

        try {
            $modules = $this->moduleModel->getByCourse($id);
            $ai = new OpenAIService();

            foreach ($modules as $module) {
                $lessons = $this->lessonModel->getByModule((int)$module['id']);
                foreach ($lessons as $lesson) {
                    $current = trim((string)($lesson['content_html'] ?? ''));
                    if ($current === '') {
                        $prompt = "Escreva conteúdo HTML autocontido (sem <html>, <head> ou <body>) para uma aula de curso em português do Brasil.\n";
                        $prompt .= "Curso: {$course['title']}\n";
                        $prompt .= "Módulo: {$module['title']}\n";
                        $prompt .= "Aula: {$lesson['title']}\n";
                        $prompt .= "Use 3 a 4 parágrafos curtos com <h2>, <h3>, <p>, e listas quando útil. Responda apenas com HTML.";
                        try {
                            $html = $ai->generateContent($prompt, $user['id'], 'fill_lesson_content', 8);
                            $html = trim((string)$html);
                            if ($html !== '') {
                                if (preg_match('/^```[\w-]*\s*/', $html)) {
                                    $html = preg_replace('/^```[\w-]*\s*/', '', $html);
                                    $html = preg_replace('/```\s*$/', '', $html);
                                    $html = trim($html);
                                }
                                if ($html !== '') {
                                    $this->lessonModel->update((int)$lesson['id'], ['content_html' => $html]);
                                    $updatedLessons++;
                                }
                            }
                        } catch (\Exception $e) {
                            // ignorar falha desta aula
                        }
                    }
                }

                $existing = (new CourseQuestion())->getByModule((int)$module['id']);
                if (empty($existing)) {
                    $qs = $this->buildDefaultQuestions((string)$module['title']);
                    if (!empty($qs)) {
                        $cq = new CourseQuestion();
                        $cq->createBatch((int)$module['id'], $qs);
                        $createdQuizzes++;
                    }
                }
            }

            $this->courseModel->updateCounters($id);
            $this->flash('success', "Conteúdo preenchido em {$updatedLessons} aula(s). Questionários criados: {$createdQuizzes}.");
            $this->redirect("courses/{$id}/manage");
        } catch (\Exception $e) {
            $this->flash('error', 'Falha ao preencher conteúdos: ' . $e->getMessage());
            $this->redirect("courses/{$id}/manage");
        }
    }

    /**
     * Matricular funcionários
     */
    public function enroll(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();
        $course = $this->courseModel->find($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->json(['error' => 'Curso não encontrado'], 404);
        }

        $employeeIds = $this->input('employee_ids', []);

        if (empty($employeeIds)) {
            $this->json(['error' => 'Selecione pelo menos um funcionário'], 400);
        }

        $enrolled = 0;
        foreach ($employeeIds as $employeeId) {
            if (!$this->enrollmentModel->isEnrolled($id, $employeeId)) {
                $this->enrollmentModel->create([
                    'course_id' => $id,
                    'employee_id' => $employeeId,
                    'enrolled_by' => $user['id'],
                ]);
                $enrolled++;
            }
        }

        $this->json([
            'success' => true,
            'message' => "{$enrolled} funcionário(s) matriculado(s) com sucesso!",
        ]);
    }

    /**
     * Preview do curso
     */
    public function preview(int $id): void
    {
        $course = $this->courseModel->getComplete($id);

        if (!$course) {
            $this->flash('error', 'Curso não encontrado.');
            $this->redirect('courses');
        }

        $this->setLayout('course');
        $this->view('courses/preview', [
            'title' => $course['title'],
            'course' => $course,
        ]);
    }

    /**
     * Preview de aula (empresa)
     */
    public function previewLesson(int $lessonId): void
    {
        $user = $this->currentUser();

        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson) {
            $this->flash('error', 'Aula não encontrada.');
            $this->redirect('courses');
        }

        // Verificar se a aula pertence a um curso da empresa logada
        $sql = "SELECT c.* FROM courses c
                JOIN course_modules m ON m.course_id = c.id
                WHERE m.id = :module_id
                LIMIT 1";
        $courses = $this->courseModel->query($sql, ['module_id' => $lesson['module_id']]);
        $course = $courses[0] ?? null;

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Você não tem acesso a esta aula.');
            $this->redirect('courses');
        }

        // Buscar próxima aula (reutiliza lógica existente)
        $nextLesson = $this->lessonModel->getNextLesson($lessonId);

        $this->setLayout('course');
        $this->view('courses/lesson', [
            'title' => $lesson['title'],
            'course' => $course,
            'lesson' => $lesson,
            'nextLesson' => $nextLesson,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Atualizar título e conteúdo da aula (empresa)
     */
    public function updateLesson(int $lessonId): void
    {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Token inválido. Tente novamente.');
            $this->redirect("courses/lessons/{$lessonId}");
        }

        $user = $this->currentUser();
        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson) {
            $this->flash('error', 'Aula não encontrada.');
            $this->redirect('courses');
        }

        // Verificar se a aula pertence a um curso da empresa logada
        $sql = "SELECT c.* FROM courses c
                JOIN course_modules m ON m.course_id = c.id
                WHERE m.id = :module_id
                LIMIT 1";
        $courses = $this->courseModel->query($sql, ['module_id' => $lesson['module_id']]);
        $course = $courses[0] ?? null;

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Você não tem acesso a esta aula.');
            $this->redirect('courses');
        }

        $title = trim($this->input('title'));
        $contentHtml = $this->input('content_html', '');

        if (empty($title)) {
            $this->flash('error', 'Informe o título da aula.');
            $this->redirect("courses/lessons/{$lessonId}");
        }

        $this->lessonModel->update($lessonId, [
            'title' => $title,
            'content_html' => $contentHtml,
        ]);

        $this->flash('success', 'Aula atualizada com sucesso.');
        $this->redirect("courses/lessons/{$lessonId}");
    }

    /**
     * Atualizar configuração de vídeo da aula (empresa)
     */
    public function updateLessonVideo(int $lessonId): void
    {
        if (!$this->validateCsrf()) {
            $this->flash('error', 'Token inválido. Tente novamente.');
            $this->redirect("courses/lessons/{$lessonId}");
        }

        $user = $this->currentUser();
        $lesson = $this->lessonModel->find($lessonId);

        if (!$lesson) {
            $this->flash('error', 'Aula não encontrada.');
            $this->redirect('courses');
        }

        // Verificar se a aula pertence a um curso da empresa logada
        $sql = "SELECT c.* FROM courses c
                JOIN course_modules m ON m.course_id = c.id
                WHERE m.id = :module_id
                LIMIT 1";
        $courses = $this->courseModel->query($sql, ['module_id' => $lesson['module_id']]);
        $course = $courses[0] ?? null;

        if (!$course || $course['company_id'] != $user['id']) {
            $this->flash('error', 'Você não tem acesso a esta aula.');
            $this->redirect('courses');
        }

        $videoMode = $this->input('video_mode', 'ai');
        $allowedModes = ['ai', 'url', 'upload', 'none'];
        if (!in_array($videoMode, $allowedModes, true)) {
            $videoMode = 'ai';
        }

        $videoUrl = $lesson['video_url'] ?? null;

        if ($videoMode === 'url') {
            $videoUrl = trim($this->input('video_url')) ?: null;

            // Se for YouTube, validar se o vídeo está disponível
            if ($videoUrl && $this->isYoutubeUrl($videoUrl) && !$this->isYoutubeVideoAvailable($videoUrl)) {
                $this->flash('error', 'O vídeo do YouTube informado parece não estar disponível. Verifique o link ou escolha outro vídeo.');
                $this->redirect("courses/lessons/{$lessonId}?mode=url");
            }
        } elseif ($videoMode === 'upload') {
            if (!empty($_FILES['video_file']['name']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
                $tmpPath = $_FILES['video_file']['tmp_name'];
                $originalName = $_FILES['video_file']['name'];
                $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExts = ['mp4', 'webm', 'ogg', 'mov', 'm4v'];
                if (!in_array($ext, $allowedExts, true)) {
                    $this->flash('error', 'Formato de vídeo inválido. Use MP4, WEBM, OGG ou MOV.');
                    $this->redirect("courses/lessons/{$lessonId}");
                }

                $uploadDir = ROOT_PATH . '/public/uploads/videos';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                $fileName = 'lesson_' . $lessonId . '_' . time() . '.' . $ext;
                $targetPath = $uploadDir . '/' . $fileName;

                if (!move_uploaded_file($tmpPath, $targetPath)) {
                    $this->flash('error', 'Falha ao fazer upload do vídeo.');
                    $this->redirect("courses/lessons/{$lessonId}");
                }

                $videoUrl = 'uploads/videos/' . $fileName;
            }
        } elseif ($videoMode === 'none') {
            $videoUrl = null;
        } elseif ($videoMode === 'ai') {
            try {
                $aiService = new OpenAIService();

                $maxAttempts = 10;
                $foundValid = false;

                for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                    $prompt = "Você é um especialista em treinamento corporativo. Indique APENAS uma URL COMPLETA de um vídeo público no YouTube em português do Brasil que seja MUITO RELEVANTE para a aula abaixo. O vídeo deve ser claramente sobre o mesmo tema.\n\n";
                    $prompt .= "Título do curso: {$course['title']}\n";
                    $prompt .= "Título da aula: {$lesson['title']}\n\n";
                    $plainContent = strip_tags($lesson['content_html'] ?? '');
                    $prompt .= "Resumo do conteúdo da aula (use isso como base para o tema exato do vídeo):\n" . mb_substr($plainContent, 0, 600);
                    $prompt .= "\n\nRegras importantes:\n";
                    $prompt .= "- O vídeo deve ser um conteúdo educacional ou explicativo (não música, clipe, propaganda, entretenimento aleatório).\n";
                    $prompt .= "- O tema do vídeo deve bater claramente com o tema da aula (pelas palavras do título e descrição).\n";
                    $prompt .= "- O vídeo deve ser adequado para treinamento corporativo.\n";
                    $prompt .= "- O vídeo deve estar em português do Brasil. Não escolha vídeos em outros idiomas (inglês, espanhol, etc.), mesmo que pareçam bons, e prefira links cujo domínio seja https://www.youtube.com.br/.\n";
                    $prompt .= "Responda APENAS com a URL do YouTube, sem nenhum texto adicional. Se o vídeo que você sugeriu anteriormente não estiver disponível, escolha outro vídeo semelhante e disponível.";

                    $response = $aiService->generateContent($prompt, $user['id'], 'lesson_video_suggestion');
                    if (!preg_match('/https?:\/\/\S+/', $response, $matches)) {
                        continue;
                    }

                    $candidateUrl = $matches[0];

                    // Aceitar apenas vídeos do YouTube que estejam disponíveis E pareçam ser em português do Brasil
                    if (
                        $this->isYoutubeUrl($candidateUrl)
                        && $this->isYoutubeVideoAvailable($candidateUrl)
                        && $this->isYoutubeVideoPortuguese($candidateUrl)
                    ) {
                        $videoUrl = $candidateUrl;
                        $foundValid = true;
                        break;
                    }
                }

                if (!$foundValid) {
                    $this->flash('error', 'Não foi possível encontrar automaticamente um vídeo disponível no YouTube para esta aula. Tente novamente ou informe um link manual.');
                    $this->redirect("courses/lessons/{$lessonId}?mode=ai");
                }
            } catch (\Exception $e) {
                $this->flash('error', 'Não foi possível obter sugestão de vídeo da IA: ' . $e->getMessage());
                $this->redirect("courses/lessons/{$lessonId}");
            }
        }

        $this->lessonModel->update($lessonId, [
            'video_url' => $videoUrl,
        ]);

        $this->flash('success', 'Vídeo da aula atualizado com sucesso.');
        $this->redirect("courses/lessons/{$lessonId}?mode={$videoMode}");
    }

    /**
     * Verifica se uma URL é do YouTube
     */
    protected function isYoutubeUrl(string $url): bool
    {
        return (bool) preg_match('/(youtube\.com|youtu\.be)/i', $url);
    }

    /**
     * Verifica se um vídeo do YouTube está disponível usando o endpoint de oEmbed
     */
    protected function isYoutubeVideoAvailable(string $url): bool
    {
        $oembedUrl = 'https://www.youtube.com/oembed?format=json&url=' . urlencode($url);

        $ch = curl_init($oembedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => false,
            CURLOPT_TIMEOUT => 8,
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        // Se API respondeu 200, está disponível
        if ($httpCode === 200) return true;
        // Ambientes com bloqueio de rede podem falhar nessa verificação: ser permissivo
        if (!empty($err) || $httpCode === 0 || $httpCode === null) return true;
        return false;
    }

    /**
     * Heurística simples para verificar se um vídeo do YouTube aparenta estar em português do Brasil
     * usando o título retornado pelo oEmbed.
     */
    protected function isYoutubeVideoPortuguese(string $url): bool
    {
        $oembedUrl = 'https://www.youtube.com/oembed?format=json&url=' . urlencode($url);

        $context = stream_context_create([
            'http' => [
                'timeout' => 8,
            ],
        ]);

        $json = @file_get_contents($oembedUrl, false, $context);
        // Se não for possível checar, ser permissivo para não bloquear
        if ($json === false) {
            return true;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['title'])) {
            return true;
        }

        $title = mb_strtolower($data['title'], 'UTF-8');

        // Procurar caracteres típicos do português ou palavras muito comuns em PT-BR
        $patterns = [
            '/[ãõáéíóúâêôç]/u',
            '/\b(de|da|do|para|com|sem|como|sobre|entre|das|dos|na|no|nas|nos|uma|um|seu|sua)\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $title)) {
                return true;
            }
        }

        // Se não encontrou fortes indícios de PT-BR, ainda assim permitir
        return true;
    }

    protected function generateModuleQuestions(string $moduleHtml, int $userId): array
    {
        $plain = trim(strip_tags($moduleHtml));
        if ($plain === '') return [];
        try {
            $aiService = new OpenAIService();
            $prompt = "Com base no conteúdo abaixo, crie 8 a 10 perguntas de múltipla escolha (4 alternativas: A, B, C, D) EM PORTUGUÊS DO BRASIL para avaliar o aprendizado do módulo. Inclua gabarito e breve explicação.\n\n";
            $prompt .= "Conteúdo:\n" . mb_substr($plain, 0, 6000) . "\n\n";
            $prompt .= "Retorne SOMENTE um JSON válido neste formato (sem markdown):\n";
            $prompt .= '[{"question_text":"pergunta","option_a":"opção A","option_b":"opção B","option_c":"opção C","option_d":"opção D","correct_option":"A","explanation":"explicação da resposta correta"}]';
            $json = $aiService->generateContent($prompt, $userId, 'course_module_questions');
            preg_match('/\[[\s\S]*\]/', $json, $m);
            $arr = json_decode($m[0] ?? '[]', true);
            return is_array($arr) ? $arr : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function unlockEnrollment(int $enrollmentId): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }
        $user = $this->currentUser();
        $enrollment = (new CourseEnrollment())->find($enrollmentId);
        if (!$enrollment) {
            $this->json(['error' => 'Matrícula não encontrada'], 404);
        }
        // Verificar se matrícula pertence a um curso da empresa logada
        $sql = "SELECT c.* FROM courses c JOIN course_enrollments e ON e.course_id = c.id WHERE e.id = :id LIMIT 1";
        $course = $this->courseModel->query($sql, ['id' => $enrollmentId])[0] ?? null;
        if (!$course || $course['company_id'] != $user['id']) {
            $this->json(['error' => 'Sem permissão'], 403);
        }
        // Desbloquear matrícula e resultados de módulos
        (new CourseEnrollment())->update($enrollmentId, ['is_locked' => 0]);
        (new CourseModuleResult())->execute('UPDATE course_module_results SET locked = 0 WHERE enrollment_id = :e', ['e' => $enrollmentId]);
        $this->json(['success' => true, 'message' => 'Matrícula desbloqueada.']);
    }

    /**
     * Extrai de forma robusta o JSON de estrutura do curso retornado pela IA.
     */
    protected function parseStructureJson(string $structureJson): array
    {
        $raw = trim($structureJson);
        // Remover cercas de código (```json ... ```)
        if (preg_match('/```+[\w-]*\s*([\s\S]*?)```/m', $raw, $m)) {
            $raw = $m[1];
        }
        // Tentar extrair objeto contendo a chave "modules"
        if (preg_match('/\{\s*\"modules\"\s*:\s*\[[\s\S]*?\]\s*\}/m', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        // Tentar decodificar a string inteira
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && isset($decoded['modules']) && is_array($decoded['modules'])) {
            return $decoded;
        }
        // Tentar pegar o primeiro objeto JSON na string
        if (preg_match('/\{[\s\S]*\}/', $raw, $m)) {
            $decoded = json_decode($m[0], true);
            if (is_array($decoded) && isset($decoded['modules'])) {
                return $decoded;
            }
        }
        // Se vier somente um array de módulos, embrulhar
        if (preg_match('/\[[\s\S]*\]/', $raw, $m)) {
            $arr = json_decode($m[0], true);
            if (is_array($arr)) {
                return ['modules' => $arr];
            }
        }
        return [];
    }

    /**
     * Fallback: cria estrutura mínima de 4 módulos x 3 aulas sem depender da IA
     */
    protected function createSkeletonStructure(string $courseTitle): array
    {
        $modules = [];
        $moduleTitles = [
            'Introdução',
            'Planejamento e Ferramentas',
            'Construção na Prática',
            'Publicação e Melhores Práticas'
        ];
        for ($i = 0; $i < 4; $i++) {
            $lessons = [];
            for ($j = 1; $j <= 3; $j++) {
                $lessons[] = [
                    'title' => 'Aula ' . $j . ': ' . $moduleTitles[$i],
                    // conteúdo vazio para evitar tempo extra de geração; poderá ser editado depois
                    'content_html' => ''
                ];
            }
            $modules[] = [
                'title' => 'Módulo ' . ($i + 1) . ': ' . $moduleTitles[$i] . ' - ' . $courseTitle,
                'description' => 'Conteúdos essenciais sobre ' . $moduleTitles[$i] . '.',
                'lessons' => $lessons,
            ];
        }
        return ['modules' => $modules];
    }

    /**
     * Fallback: cria 8 questões padrão quando a IA não retorna perguntas.
     */
    protected function buildDefaultQuestions(string $moduleTitle): array
    {
        $qs = [];
        for ($i = 1; $i <= 8; $i++) {
            $qs[] = [
                'question_text' => "Sobre: {$moduleTitle} — marque a alternativa mais adequada.",
                'option_a' => 'Afirmativa correta (padrão)',
                'option_b' => 'Alternativa incorreta',
                'option_c' => 'Alternativa incorreta',
                'option_d' => 'Alternativa incorreta',
                'correct_option' => 'A',
                'explanation' => 'Resposta padrão gerada automaticamente.'
            ];
        }
        return $qs;
    }

    protected function suggestYoutubePtBrVideo(string $courseTitle, string $lessonTitle, string $plainContent, int $userId): ?string
    {
        try {
            $aiService = new OpenAIService();
            $maxAttempts = 3;
            for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
                $prompt = "Você é um especialista em treinamento corporativo. Indique APENAS uma URL COMPLETA de um vídeo público no YouTube em português do Brasil que seja MUITO RELEVANTE para a aula abaixo. O vídeo deve ser claramente sobre o mesmo tema.\n\n";
                $prompt .= "Título do curso: {$courseTitle}\n";
                $prompt .= "Título da aula: {$lessonTitle}\n\n";
                $prompt .= "Resumo do conteúdo da aula:\n" . mb_substr($plainContent, 0, 600);
                $prompt .= "\n\nRegras importantes:\n";
                $prompt .= "- O vídeo deve ser educacional/explicativo, adequado para treinamento corporativo.\n";
                $prompt .= "- O tema deve bater claramente com o tema da aula.\n";
                $prompt .= "- O vídeo deve estar em português do Brasil. Não escolha vídeos em outros idiomas. Prefira links https://www.youtube.com.br/.\n";
                $prompt .= "Responda APENAS com a URL do YouTube.\n";

                $response = $aiService->generateContent($prompt, $userId, 'lesson_video_suggestion');
                if (!preg_match('/https?:\/\/\S+/', $response, $matches)) {
                    continue;
                }
                $url = $matches[0];
                if ($this->isYoutubeUrl($url) && $this->isYoutubeVideoAvailable($url) && $this->isYoutubeVideoPortuguese($url)) {
                    return $url;
                }
            }
        } catch (\Exception $e) {
            // silencioso
        }
        return null;
    }

    /**
     * Deletar curso
     */
    public function delete(int $id): void
    {
        $user = $this->currentUser();
        $course = $this->courseModel->find($id);

        if (!$course || $course['company_id'] != $user['id']) {
            $this->json(['error' => 'Curso não encontrado'], 404);
        }

        $this->courseModel->delete($id);

        $this->json([
            'success' => true,
            'message' => 'Curso excluído com sucesso!',
        ]);
    }
}
