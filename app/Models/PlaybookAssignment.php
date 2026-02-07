<?php

namespace App\Models;

use App\Core\Model;

/**
 * Model: PlaybookAssignment (playbook_assignments)
 */
class PlaybookAssignment extends Model
{
    protected string $table = 'playbook_assignments';
    
    protected array $fillable = [
        'playbook_id',
        'employee_id',
        'assigned_by',
        'due_date',
        'status',
        'started_at',
        'completed_at',
        'score',
        'passed',
        'attempts'
    ];

    /**
     * Buscar atribuições do funcionário
     */
    public function getByEmployee(int $employeeId): array
    {
        $sql = "SELECT a.*, p.title as playbook_title, p.description as playbook_description
                FROM {$this->table} a
                JOIN company_playbooks p ON a.playbook_id = p.id
                WHERE a.employee_id = :employee_id
                ORDER BY a.created_at DESC";
        
        return $this->query($sql, ['employee_id' => $employeeId]);
    }

    /**
     * Buscar atribuições do playbook
     */
    public function getByPlaybook(int $playbookId): array
    {
        $sql = "SELECT a.*, u.name as employee_name, u.email as employee_email
                FROM {$this->table} a
                JOIN user_profiles u ON a.employee_id = u.id
                WHERE a.playbook_id = :playbook_id
                ORDER BY a.created_at DESC";
        
        return $this->query($sql, ['playbook_id' => $playbookId]);
    }

    /**
     * Verificar se funcionário já foi atribuído ao playbook
     */
    public function isAssigned(int $playbookId, int $employeeId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE playbook_id = :playbook_id AND employee_id = :employee_id";
        
        $result = $this->query($sql, [
            'playbook_id' => $playbookId,
            'employee_id' => $employeeId
        ]);
        
        return ($result[0]['total'] ?? 0) > 0;
    }

    /**
     * Iniciar treinamento
     */
    public function start(int $assignmentId): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET status = 'in_progress', started_at = NOW() WHERE id = :id",
            ['id' => $assignmentId]
        );
    }

    /**
     * Completar treinamento
     */
    public function complete(int $assignmentId, float $score, bool $passed): bool
    {
        return $this->execute(
            "UPDATE {$this->table} SET status = 'completed', completed_at = NOW(), 
             score = :score, passed = :passed, attempts = attempts + 1 WHERE id = :id",
            ['id' => $assignmentId, 'score' => $score, 'passed' => $passed]
        );
    }

    /**
     * Resetar treinamento
     */
    public function reset(int $assignmentId): bool
    {
        // Deletar respostas anteriores
        $this->execute(
            "DELETE FROM playbook_answers WHERE assignment_id = :id",
            ['id' => $assignmentId]
        );
        
        return $this->update($assignmentId, [
            'status' => 'pending',
            'started_at' => null,
            'completed_at' => null,
            'score' => null,
            'passed' => null
        ]);
    }

    /**
     * Estatísticas por empresa
     */
    public function getStatsByCompany(int $companyId): array
    {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN a.passed = 1 THEN 1 ELSE 0 END) as passed,
                    AVG(a.score) as avg_score
                FROM {$this->table} a
                JOIN company_playbooks p ON a.playbook_id = p.id
                WHERE p.company_id = :company_id";
        
        $result = $this->query($sql, ['company_id' => $companyId]);
        return $result[0] ?? [];
    }
}
