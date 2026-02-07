<?php

namespace App\Middlewares;

/**
 * GuestMiddleware - Verificar se usuário NÃO está autenticado
 */
class GuestMiddleware
{
    /**
     * Verificar se é visitante (não logado)
     */
    public function handle(): bool
    {
        // Só considera logado se existir $_SESSION['user']['id']
        if (isset($_SESSION['user']['id'])) {
            $userType = $_SESSION['user']['user_type'] ?? 'company';
            
            // Redirecionar para dashboard apropriado
            $redirects = [
                'admin'        => '/site-freelancePro/admin/dashboard',
                'company'      => '/site-freelancePro/dashboard',
                'professional' => '/site-freelancePro/professional/dashboard',
                'employee'     => '/site-freelancePro/employee/dashboard',
            ];
            
            $redirect = $redirects[$userType] ?? '/site-freelancePro/dashboard';
            header('Location: ' . $redirect);
            exit;
        }
        
        return true;
    }
}
