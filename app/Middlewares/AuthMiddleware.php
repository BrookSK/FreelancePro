<?php

namespace App\Middlewares;

/**
 * AuthMiddleware - Verificar autenticação
 */
class AuthMiddleware
{
    /**
     * Verificar se usuário está autenticado
     */
    public function handle(): bool
    {
        // Considera usuário autenticado apenas se houver dados completos em $_SESSION['user']
        if (!isset($_SESSION['user']['id'])) {
            // Limpar resquício de sessões antigas que só usavam user_id
            unset($_SESSION['user_id']);
            header('Location: /site-freelancePro/login');
            exit;
        }
        
        return true;
    }
}
