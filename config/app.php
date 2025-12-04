<?php
/**
 * Configurações Gerais da Aplicação
 */

$__baseUrl = (function () {
    $scheme = 'http';
    if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = $_SERVER['HTTP_X_FORWARDED_PROTO'];
    } elseif (isset($_SERVER['HTTPS']) && (($_SERVER['HTTPS'] === 'on') || ($_SERVER['HTTPS'] === 1))) {
        $scheme = 'https';
    }
    if (!isset($_SERVER['HTTP_HOST'])) {
        return 'http://localhost:8080/site-freelancePro';
    }
    $host = $_SERVER['HTTP_HOST'];
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $basePath = rtrim(str_replace('/public', '', dirname($script)), '/');
    return rtrim($scheme . '://' . $host . $basePath, '/');
})();

return [
    // Nome da aplicação
    'name' => 'FreelancePro',
    
    // URL base da aplicação
    'url' => $__baseUrl,
    
    // Ambiente: development ou production
    'environment' => 'development',
    
    // Debug mode
    'debug' => true,
    
    // Timezone
    'timezone' => 'America/Sao_Paulo',
    
    // Idioma padrão
    'locale' => 'pt_BR',
    
    // Chave secreta para sessões e tokens
    'secret_key' => 'sua_chave_secreta_aqui_mude_em_producao',
    
    // Configurações de sessão
    'session' => [
        'name'     => 'freelancepro_session',
        'lifetime' => 7200, // 2 horas
        'path'     => '/',
        'secure'   => false, // true em produção com HTTPS
        'httponly' => true,
    ],
    
    // Configurações de upload
    'upload' => [
        'path'          => ROOT_PATH . '/public/uploads',
        'max_size'      => 10485760, // 10MB
        'allowed_types' => ['pdf', 'doc', 'docx', 'txt', 'mp3', 'wav', 'ogg'],
    ],
    
    // Valores de pagamento
    'payment' => [
        'registration_fee'  => 29.90,  // Taxa de registro
        'monthly_fee'       => 29.90,  // Assinatura mensal
        'playbook_fee'      => 19.90,  // Taxa por playbook
        'freelancer_fee'    => 0.07,   // 7% do valor do projeto
    ],
    
    // Tipos de usuário
    'user_types' => [
        'admin'        => 'Administrador',
        'company'      => 'Empresa',
        'professional' => 'Profissional/Freelancer',
        'employee'     => 'Funcionário',
    ],
];
