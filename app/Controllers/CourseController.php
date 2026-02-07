<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Course;
use App\Models\CourseModule;
use App\Models\CourseLesson;
use App\Models\CourseEnrollment;
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
            $aiService = new OpenAIService();

            // Gerar estrutura do curso com conteúdo detalhado em HTML
            $structurePrompt = "Você é um instrutor corporativo. Crie a estrutura COMPLETA de um curso online em português do Brasil sobre o tema abaixo.\n\n";
            $structurePrompt .= "Título do curso: {$title}\n";
            $structurePrompt .= "Descrição do curso: {$description}\n\n";
            if (!empty($baseContent)) {
                $structurePrompt .= "Conteúdo base / referências que devem ser usadas:\n{$baseContent}\n\n";
            }
            $structurePrompt .= "Regras importantes:\n";
            $structurePrompt .= "- O curso deve ter EXATAMENTE 4 módulos e CADA módulo deve ter EXATAMENTE 3 aulas.\n";
            $structurePrompt .= "- Para cada aula, escreva um conteúdo HTML COMPLETO em português, com pelo menos 6 parágrafos, usando tags <h2>, <h3>, <p>, <ul>, <li>, etc.\n";
            $structurePrompt .= "- O HTML deve ser autocontido, sem usar <html>, <head> ou <body>.\n";
            $structurePrompt .= "- Não inclua explicações fora do JSON.\n";
            if ($videoSource === 'ai') {
                $structurePrompt .= "Além disso, para cada aula indique também um campo video_url com uma URL COMPLETA de um vídeo público relevante no YouTube em português do Brasil (não escolha vídeos em outros idiomas), preferencialmente com domínio https://www.youtube.com.br/.\n";
                $structurePrompt .= "Retorne APENAS um JSON VÁLIDO no seguinte formato, sem markdown, sem comentários, sem texto antes ou depois:\n";
                $structurePrompt .= '{"modules":[{"title":"título do módulo","description":"descrição do módulo","lessons":[{"title":"título da aula","content_html":"conteúdo HTML completo da aula","video_url":"https://www.youtube.com/..."}]}]}';
            } else {
                $structurePrompt .= "Retorne APENAS um JSON VÁLIDO no seguinte formato, sem markdown, sem comentários, sem texto antes ou depois:\n";
                $structurePrompt .= '{"modules":[{"title":"título do módulo","description":"descrição do módulo","lessons":[{"title":"título da aula","content_html":"conteúdo HTML completo da aula"}]}]}';
            }

            $structureJson = $aiService->generateContent($structurePrompt, $user['id']);

            // Extrair JSON
            preg_match('/\{[\s\S]*\}/', $structureJson, $matches);
            $structure = json_decode($matches[0] ?? '{}', true);

            if (empty($structure['modules'])) {
                throw new \Exception('Não foi possível gerar a estrutura do curso');
            }

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
                    $this->lessonModel->create([
                        'module_id' => $moduleId,
                        'title' => $lessonData['title'],
                        'content_html' => $lessonData['content_html'] ?? '',
                        'video_url' => $videoSource === 'ai' ? ($lessonData['video_url'] ?? null) : null,
                        'order_number' => $lessonOrder++,
                    ]);
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
            $structurePrompt .= "- Para cada aula, escreva um conteúdo HTML COMPLETO em português, com pelo menos 6 parágrafos, usando tags <h2>, <h3>, <p>, <ul>, <li>, etc.\n";
            $structurePrompt .= "- O HTML deve ser autocontido, sem usar <html>, <head> ou <body>.\n";
            $structurePrompt .= "- Não inclua explicações fora do JSON.\n";
            $structurePrompt .= "Além disso, para cada aula indique também um campo video_url com uma URL COMPLETA de um vídeo público relevante no YouTube em português do Brasil (não escolha vídeos em outros idiomas), preferencialmente com domínio https://www.youtube.com.br/.\n";
            $structurePrompt .= "Retorne APENAS um JSON VÁLIDO no seguinte formato, sem markdown, sem comentários, sem texto antes ou depois:\n";
            $structurePrompt .= '{"modules":[{"title":"título do módulo","description":"descrição do módulo","lessons":[{"title":"título da aula","content_html":"conteúdo HTML completo da aula","video_url":"https://www.youtube.com/..."}]}]}';

            $structureJson = $aiService->generateContent($structurePrompt, $user['id'], 'regenerate_course');

            // Extrair JSON
            preg_match('/\{[\s\S]*\}/', $structureJson, $matches);
            $structure = json_decode($matches[0] ?? '{}', true);

            if (empty($structure['modules'])) {
                throw new \Exception('Não foi possível gerar a nova estrutura do curso');
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
                    $this->lessonModel->create([
                        'module_id' => $moduleId,
                        'title' => $lessonData['title'],
                        'content_html' => $lessonData['content_html'] ?? '',
                        'video_url' => $lessonData['video_url'] ?? null,
                        'order_number' => $lessonOrder++,
                    ]);
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
    public function show(int $id): void
    {
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
    public function publish(int $id): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();
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
        curl_close($ch);

        return $httpCode === 200;
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
        if ($json === false) {
            return false;
        }

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['title'])) {
            return false;
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

        return false;
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
