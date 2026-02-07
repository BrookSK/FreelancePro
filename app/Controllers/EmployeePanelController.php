<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\User;
use App\Models\Playbook;
use App\Models\PlaybookAssignment;
use App\Models\PlaybookAnswer;
use App\Models\PlaybookQuestion;
use App\Models\CourseEnrollment;
use App\Models\Course;
use App\Models\CourseLesson;

/**
 * EmployeePanelController - Área do Funcionário
 */
class EmployeePanelController extends Controller
{
    /**
     * Dashboard do funcionário
     */
    public function dashboard(): void
    {
        $user = $this->currentUser();

        // Buscar treinamentos pendentes
        $assignmentModel = new PlaybookAssignment();
        $assignments = $assignmentModel->getByEmployee($user['id']);

        $pending = array_filter($assignments, fn($a) => $a['status'] !== 'completed');
        $completed = array_filter($assignments, fn($a) => $a['status'] === 'completed');

        // Buscar cursos matriculados
        $enrollmentModel = new CourseEnrollment();
        $enrollments = $enrollmentModel->getByEmployee($user['id']);

        $this->setLayout('employee');
        $this->view('employee/dashboard', [
            'title' => 'Meu Painel',
            'pendingTrainings' => $pending,
            'completedTrainings' => $completed,
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Listar treinamentos do funcionário
     */
    public function trainings(): void
    {
        $user = $this->currentUser();

        $assignmentModel = new PlaybookAssignment();
        $assignments = $assignmentModel->getByEmployee($user['id']);

        $this->setLayout('employee');
        $this->view('employee/trainings', [
            'title' => 'Meus Treinamentos',
            'assignments' => $assignments,
        ]);
    }

    /**
     * Visualizar treinamento
     */
    public function viewTraining(int $assignmentId): void
    {
        $user = $this->currentUser();

        $assignmentModel = new PlaybookAssignment();
        $assignment = $assignmentModel->find($assignmentId);

        if (!$assignment || $assignment['employee_id'] != $user['id']) {
            $this->flash('error', 'Treinamento não encontrado.');
            $this->redirect('employee/trainings');
        }

        // Buscar playbook com questões
        $playbookModel = new Playbook();
        $playbook = $playbookModel->getWithQuestions($assignment['playbook_id']);

        // Marcar como iniciado se pendente
        if ($assignment['status'] === 'pending') {
            $assignmentModel->start($assignmentId);
            $assignment['status'] = 'in_progress';
        }

        // Buscar respostas anteriores se houver
        $answerModel = new PlaybookAnswer();
        $previousAnswers = $answerModel->getByAssignment($assignmentId);

        $this->setLayout('employee');
        $this->view('employee/training-detail', [
            'title' => $playbook['title'],
            'playbook' => $playbook,
            'assignment' => $assignment,
            'previousAnswers' => $previousAnswers,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    /**
     * Submeter respostas do treinamento
     */
    public function submitTraining(int $assignmentId): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();

        $assignmentModel = new PlaybookAssignment();
        $assignment = $assignmentModel->find($assignmentId);

        if (!$assignment || $assignment['employee_id'] != $user['id']) {
            $this->json(['error' => 'Treinamento não encontrado'], 404);
        }

        $answers = $this->input('answers', []);

        if (empty($answers)) {
            $this->json(['error' => 'Responda todas as questões'], 400);
        }

        // Buscar questões para verificar respostas
        $questionModel = new PlaybookQuestion();
        $questions = $questionModel->getByPlaybook($assignment['playbook_id']);

        // Processar respostas
        $processedAnswers = [];
        foreach ($questions as $question) {
            $selectedOption = $answers[$question['id']] ?? null;
            
            if ($selectedOption) {
                $isCorrect = strtoupper($selectedOption) === strtoupper($question['correct_option']);
                $processedAnswers[] = [
                    'question_id' => $question['id'],
                    'selected_option' => $selectedOption,
                    'is_correct' => $isCorrect,
                ];
            }
        }

        // Salvar respostas
        $answerModel = new PlaybookAnswer();
        $answerModel->saveBatch($assignmentId, $processedAnswers);

        // Calcular nota
        $score = $answerModel->calculateScore($assignmentId);
        $passed = $score >= 70; // 70% para aprovação

        // Completar treinamento
        $assignmentModel->complete($assignmentId, $score, $passed);

        $this->json([
            'success' => true,
            'score' => $score,
            'passed' => $passed,
            'message' => $passed 
                ? "Parabéns! Você foi aprovado com {$score}%!" 
                : "Você obteve {$score}%. Nota mínima: 70%. Tente novamente.",
        ]);
    }

    /**
     * Listar cursos do funcionário
     */
    public function courses(): void
    {
        $user = $this->currentUser();

        $enrollmentModel = new CourseEnrollment();
        $enrollments = $enrollmentModel->getByEmployee($user['id']);

        $this->setLayout('employee');
        $this->view('employee/courses', [
            'title' => 'Meus Cursos',
            'enrollments' => $enrollments,
        ]);
    }

    /**
     * Acessar curso
     */
    public function viewCourse(int $courseId): void
    {
        $user = $this->currentUser();

        $enrollmentModel = new CourseEnrollment();
        $enrollment = $enrollmentModel->getEnrollment($courseId, $user['id']);

        if (!$enrollment) {
            $this->flash('error', 'Você não está matriculado neste curso.');
            $this->redirect('employee/courses');
        }

        // Buscar curso completo
        $courseModel = new Course();
        $course = $courseModel->getComplete($courseId);

        $this->setLayout('course');
        $this->view('employee/course-view', [
            'title' => $course['title'],
            'course' => $course,
            'enrollment' => $enrollment,
        ]);
    }

    /**
     * Visualizar aula
     */
    public function viewLesson(int $lessonId): void
    {
        $user = $this->currentUser();

        $lessonModel = new CourseLesson();
        $lesson = $lessonModel->find($lessonId);

        if (!$lesson) {
            $this->flash('error', 'Aula não encontrada.');
            $this->redirect('employee/courses');
        }

        // Verificar matrícula
        // ... (verificação adicional)

        // Buscar próxima aula
        $nextLesson = $lessonModel->getNextLesson($lessonId);

        $this->setLayout('course');
        $this->view('employee/lesson-view', [
            'title' => $lesson['title'],
            'lesson' => $lesson,
            'nextLesson' => $nextLesson,
        ]);
    }

    /**
     * Marcar aula como concluída
     */
    public function completeLesson(int $lessonId): void
    {
        $user = $this->currentUser();

        // Lógica para marcar como concluída e atualizar progresso
        // ...

        $this->json([
            'success' => true,
            'message' => 'Aula concluída!',
        ]);
    }
}
