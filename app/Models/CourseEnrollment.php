<?php

namespace App\Models;

use App\Core\Model;

/**
 * Model: CourseEnrollment (course_enrollments)
 */
class CourseEnrollment extends Model
{
    protected string $table = 'course_enrollments';
    
    protected array $fillable = [
        'course_id',
        'employee_id',
        'enrolled_by',
        'status',
        'progress_percentage',
        'started_at',
        'completed_at',
        'final_score'
    ];

    /**
     * Buscar matrículas do funcionário
     */
    public function getByEmployee(int $employeeId): array
    {
        $sql = "SELECT e.*, c.title as course_title, c.description as course_description,
                       c.total_modules, c.total_lessons
                FROM {$this->table} e
                JOIN courses c ON e.course_id = c.id
                WHERE e.employee_id = :employee_id
                ORDER BY e.created_at DESC";
        
        return $this->query($sql, ['employee_id' => $employeeId]);
    }

    /**
     * Buscar matrículas do curso
     */
    public function getByCourse(int $courseId): array
    {
        $sql = "SELECT e.*, u.name as employee_name, u.email as employee_email
                FROM {$this->table} e
                JOIN user_profiles u ON e.employee_id = u.id
                WHERE e.course_id = :course_id
                ORDER BY e.created_at DESC";
        
        return $this->query($sql, ['course_id' => $courseId]);
    }

    /**
     * Verificar se funcionário está matriculado
     */
    public function isEnrolled(int $courseId, int $employeeId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE course_id = :course_id AND employee_id = :employee_id";
        
        $result = $this->query($sql, [
            'course_id' => $courseId,
            'employee_id' => $employeeId
        ]);
        
        return ($result[0]['total'] ?? 0) > 0;
    }

    /**
     * Buscar matrícula específica
     */
    public function getEnrollment(int $courseId, int $employeeId): ?array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE course_id = :course_id AND employee_id = :employee_id
                LIMIT 1";
        
        $result = $this->query($sql, [
            'course_id' => $courseId,
            'employee_id' => $employeeId
        ]);
        
        return $result[0] ?? null;
    }

    /**
     * Atualizar progresso
     */
    public function updateProgress(int $enrollmentId): bool
    {
        // Calcular progresso baseado nas aulas completadas
        $sql = "SELECT 
                    (SELECT COUNT(*) FROM course_progress WHERE enrollment_id = :id1 AND status = 'completed') as completed,
                    (SELECT COUNT(*) FROM course_lessons l 
                     JOIN course_modules m ON l.module_id = m.id
                     JOIN course_enrollments e ON m.course_id = e.course_id
                     WHERE e.id = :id2) as total";
        
        $result = $this->query($sql, ['id1' => $enrollmentId, 'id2' => $enrollmentId]);
        
        $completed = $result[0]['completed'] ?? 0;
        $total = $result[0]['total'] ?? 1;
        
        $progress = round(($completed / $total) * 100, 2);
        
        $data = ['progress_percentage' => $progress];
        
        if ($progress >= 100) {
            $data['status'] = 'completed';
            $data['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($progress > 0) {
            $data['status'] = 'in_progress';
            if (!$this->find($enrollmentId)['started_at']) {
                $data['started_at'] = date('Y-m-d H:i:s');
            }
        }
        
        return $this->update($enrollmentId, $data);
    }
}
