<?php

namespace App\Models;

use App\Core\Model;

/**
 * Model: Playbook (company_playbooks)
 */
class Playbook extends Model
{
    protected string $table = 'company_playbooks';
    
    protected array $fillable = [
        'company_id',
        'title',
        'description',
        'content_html',
        'source_type',
        'source_file',
        'rules_policies',
        'status',
        'is_paid',
        'payment_id'
    ];

    /**
     * Buscar playbooks da empresa
     */
    public function getByCompany(int $companyId): array
    {
        $sql = "SELECT * FROM {$this->table} WHERE company_id = :company_id ORDER BY created_at DESC";
        return $this->query($sql, ['company_id' => $companyId]);
    }

    /**
     * Buscar playbooks publicados da empresa
     */
    public function getPublishedByCompany(int $companyId): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE company_id = :company_id AND status = 'published' 
                ORDER BY created_at DESC";
        return $this->query($sql, ['company_id' => $companyId]);
    }

    /**
     * Contar playbooks da empresa
     */
    public function countByCompany(int $companyId): int
    {
        return $this->countWhere('company_id', $companyId);
    }

    /**
     * Buscar com questões
     */
    public function getWithQuestions(int $playbookId): ?array
    {
        $playbook = $this->find($playbookId);
        
        if ($playbook) {
            $sql = "SELECT * FROM playbook_questions WHERE playbook_id = :id ORDER BY question_number";
            $playbook['questions'] = $this->query($sql, ['id' => $playbookId]);
        }
        
        return $playbook;
    }

    /**
     * Buscar playbooks atribuídos a um funcionário
     */
    public function getAssignedToEmployee(int $employeeId): array
    {
        $sql = "SELECT p.*, pa.id as assignment_id, pa.status as assignment_status, 
                       pa.score, pa.passed, pa.due_date
                FROM {$this->table} p
                JOIN playbook_assignments pa ON p.id = pa.playbook_id
                WHERE pa.employee_id = :employee_id
                ORDER BY pa.created_at DESC";
        
        return $this->query($sql, ['employee_id' => $employeeId]);
    }
}
