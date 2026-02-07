<?php

namespace App\Middlewares;

/**
 * AdminMiddleware - Verificar se é administrador
 */
class AdminMiddleware
{
    /**
     * Verificar se usuário é admin
     */
    public function handle(): bool
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /site-freelancePro/login');
            exit;
        }
        
        if (($_SESSION['user']['user_type'] ?? '') !== 'admin') {
            header('Location: /site-freelancePro/dashboard');
            exit;
        }
        
        return true;
    }
}
