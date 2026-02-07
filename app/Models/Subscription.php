<?php

namespace App\Models;

use App\Core\Model;

/**
 * Model: Subscription (company_subscriptions)
 */
class Subscription extends Model
{
    protected string $table = 'company_subscriptions';
    
    protected array $fillable = [
        'company_id',
        'plan_id',
        'status',
        'assas_subscription_id',
        'assas_customer_id',
        'current_period_start',
        'current_period_end',
        'cancelled_at'
    ];

    /**
     * Buscar assinatura ativa da empresa
     */
    public function getActiveByCompany(int $companyId): ?array
    {
        $sql = "SELECT s.*, p.name as plan_name, p.price as plan_price, p.features as plan_features
                FROM {$this->table} s
                JOIN subscription_plans p ON s.plan_id = p.id
                WHERE s.company_id = :company_id AND s.status = 'active'
                LIMIT 1";
        
        $result = $this->query($sql, ['company_id' => $companyId]);
        return $result[0] ?? null;
    }

    /**
     * Buscar por empresa
     */
    public function getByCompany(int $companyId): array
    {
        return $this->where('company_id', $companyId);
    }

    /**
     * Verificar se empresa tem assinatura ativa
     */
    public function hasActiveSubscription(int $companyId): bool
    {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} 
                WHERE company_id = :company_id AND status = 'active'";
        
        $result = $this->query($sql, ['company_id' => $companyId]);
        return ($result[0]['total'] ?? 0) > 0;
    }

    /**
     * Cancelar assinatura
     */
    public function cancel(int $subscriptionId): bool
    {
        return $this->update($subscriptionId, [
            'status' => 'cancelled',
            'cancelled_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Buscar assinaturas vencidas
     */
    public function getExpired(): array
    {
        $sql = "SELECT * FROM {$this->table} 
                WHERE status = 'active' AND current_period_end < NOW()";
        return $this->query($sql);
    }
}
