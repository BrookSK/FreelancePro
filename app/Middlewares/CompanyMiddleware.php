<?php

namespace App\Middlewares;

/**
 * CompanyMiddleware - Verificar se é empresa
 */
class CompanyMiddleware
{
    /**
     * Verificar se usuário é empresa
     */
    public function handle(): bool
    {
        // Usuário precisa ter sessão completa
        if (!isset($_SESSION['user']['id'])) {
            unset($_SESSION['user_id']);
            header('Location: /site-freelancePro/login');
            exit;
        }
        
        if (($_SESSION['user']['user_type'] ?? '') !== 'company') {
            header('Location: /site-freelancePro/login');
            exit;
        }
        
        return true;
    }
}
