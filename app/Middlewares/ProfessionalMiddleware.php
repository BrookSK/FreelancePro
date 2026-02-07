<?php

namespace App\Middlewares;

/**
 * ProfessionalMiddleware - Verificar se é profissional/freelancer
 */
class ProfessionalMiddleware
{
    /**
     * Verificar se usuário é profissional
     */
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /site-freelancePro/login');
            exit;
        }
        
        if (($_SESSION['user']['user_type'] ?? '') !== 'professional') {
            header('Location: /site-freelancePro/login');
            exit;
        }
        
        return true;
    }
}
