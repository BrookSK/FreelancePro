<?php

use App\Core\Application;

$app = Application::getInstance();
$router = $app->getRouter();

// ==========================================
// ROTAS PÚBLICAS (GUEST)
// ==========================================

$router->get('', 'AuthController@loginPage', ['GuestMiddleware']);
$router->get('login', 'AuthController@loginPage', ['GuestMiddleware']);
$router->post('login', 'AuthController@login');
$router->get('register', 'AuthController@registerPage', ['GuestMiddleware']);
$router->post('register', 'AuthController@register');
$router->get('forgot-password', 'AuthController@forgotPasswordPage', ['GuestMiddleware']);
$router->post('forgot-password', 'AuthController@forgotPassword');
$router->get('reset-password', 'AuthController@resetPasswordPage', ['GuestMiddleware']);
$router->post('reset-password', 'AuthController@resetPassword');
$router->get('logout', 'AuthController@logout');

// Login funcionário
$router->get('employee/login', 'AuthController@employeeLoginPage', ['GuestMiddleware']);
$router->post('employee/login', 'AuthController@employeeLogin');

// ==========================================
// ROTAS DA EMPRESA (COMPANY)
// ==========================================

$router->get('dashboard', 'DashboardController@index', ['CompanyMiddleware']);

// Playbooks
$router->get('playbooks', 'PlaybookController@index', ['CompanyMiddleware']);
$router->get('playbooks/create', 'PlaybookController@create', ['CompanyMiddleware']);
$router->post('playbooks/generate', 'PlaybookController@generate', ['CompanyMiddleware']);
$router->get('playbooks/{id}', 'PlaybookController@show', ['CompanyMiddleware']);
$router->post('playbooks/{id}/publish', 'PlaybookController@publish', ['CompanyMiddleware']);
$router->get('playbooks/{id}/assign', 'PlaybookController@assignPage', ['CompanyMiddleware']);
$router->post('playbooks/{id}/assign', 'PlaybookController@assign', ['CompanyMiddleware']);
$router->delete('playbooks/{id}', 'PlaybookController@delete', ['CompanyMiddleware']);

// Cursos
$router->get('courses', 'CourseController@index', ['CompanyMiddleware']);
$router->get('courses/create', 'CourseController@create', ['CompanyMiddleware']);
$router->post('courses/generate', 'CourseController@generate', ['CompanyMiddleware']);
$router->get('courses/{id}', 'CourseController@show', ['CompanyMiddleware']);
$router->get('courses/{id}/manage', 'CourseController@manage', ['CompanyMiddleware']);
$router->get('courses/{id}/preview', 'CourseController@preview', ['CompanyMiddleware']);
$router->get('courses/lessons/{id}', 'CourseController@previewLesson', ['CompanyMiddleware']);
$router->post('courses/lessons/{id}', 'CourseController@updateLesson', ['CompanyMiddleware']);
$router->post('courses/lessons/{id}/video', 'CourseController@updateLessonVideo', ['CompanyMiddleware']);
$router->post('courses/{id}/publish', 'CourseController@publish', ['CompanyMiddleware']);
$router->post('courses/{id}/regenerate', 'CourseController@regenerate', ['CompanyMiddleware']);
$router->post('courses/{id}/enroll', 'CourseController@enroll', ['CompanyMiddleware']);
$router->delete('courses/{id}', 'CourseController@delete', ['CompanyMiddleware']);

// Funcionários
$router->get('employees', 'EmployeeController@index', ['CompanyMiddleware']);
$router->get('employees/create', 'EmployeeController@create', ['CompanyMiddleware']);
$router->post('employees', 'EmployeeController@store', ['CompanyMiddleware']);
$router->get('employees/{id}', 'EmployeeController@show', ['CompanyMiddleware']);
$router->get('employees/{id}/edit', 'EmployeeController@edit', ['CompanyMiddleware']);
$router->post('employees/{id}', 'EmployeeController@update', ['CompanyMiddleware']);
$router->delete('employees/{id}', 'EmployeeController@delete', ['CompanyMiddleware']);
$router->post('employees/{id}/training/{assignmentId}/reset', 'EmployeeController@resetTraining', ['CompanyMiddleware']);

// Projetos Freelancer
$router->get('projects', 'ProjectController@index', ['CompanyMiddleware']);
$router->get('projects/create', 'ProjectController@create', ['CompanyMiddleware']);
$router->post('projects', 'ProjectController@store', ['CompanyMiddleware']);
$router->get('projects/{id}', 'ProjectController@show', ['CompanyMiddleware']);
$router->post('projects/{id}/cancel', 'ProjectController@cancel', ['CompanyMiddleware']);
$router->post('projects/{id}/proposals/{proposalId}/accept', 'ProjectController@acceptProposal', ['CompanyMiddleware']);
$router->post('projects/{id}/proposals/{proposalId}/reject', 'ProjectController@rejectProposal', ['CompanyMiddleware']);
$router->post('projects/{id}/proposals/{proposalId}/message', 'ProjectController@sendMessage', ['CompanyMiddleware']);

// Contratos
$router->get('contracts', 'ProjectController@contracts', ['CompanyMiddleware']);
$router->get('contracts/{id}', 'ProjectController@viewContract', ['CompanyMiddleware']);
$router->post('contracts/{id}/complete', 'ProjectController@completeContract', ['CompanyMiddleware']);
$router->post('contracts/{id}/review', 'ProjectController@submitReview', ['CompanyMiddleware']);

// Pagamentos
$router->get('plans', 'PaymentController@plans', ['CompanyMiddleware']);
$router->get('checkout/{planId}', 'PaymentController@checkout', ['CompanyMiddleware']);
$router->post('checkout', 'PaymentController@processCheckout', ['CompanyMiddleware']);
$router->get('payment/success', 'PaymentController@success', ['CompanyMiddleware']);
$router->get('payment/failure', 'PaymentController@failure', ['CompanyMiddleware']);
$router->get('payment/history', 'PaymentController@history', ['CompanyMiddleware']);
$router->get('subscription', 'PaymentController@subscription', ['CompanyMiddleware']);
$router->post('subscription/cancel', 'PaymentController@cancelSubscription', ['CompanyMiddleware']);

// ==========================================
// ROTAS DO FUNCIONÁRIO (EMPLOYEE)
// ==========================================

$router->get('employee/dashboard', 'EmployeePanelController@dashboard', ['EmployeeMiddleware']);
$router->get('employee/trainings', 'EmployeePanelController@trainings', ['EmployeeMiddleware']);
$router->get('employee/trainings/{id}', 'EmployeePanelController@viewTraining', ['EmployeeMiddleware']);
$router->post('employee/trainings/{id}/submit', 'EmployeePanelController@submitTraining', ['EmployeeMiddleware']);
$router->get('employee/courses', 'EmployeePanelController@courses', ['EmployeeMiddleware']);
$router->get('employee/courses/{id}', 'EmployeePanelController@viewCourse', ['EmployeeMiddleware']);
$router->get('employee/lessons/{id}', 'EmployeePanelController@viewLesson', ['EmployeeMiddleware']);
$router->post('employee/lessons/{id}/complete', 'EmployeePanelController@completeLesson', ['EmployeeMiddleware']);

// ==========================================
// ROTAS DO PROFISSIONAL (PROFESSIONAL)
// ==========================================

$router->get('professional/dashboard', 'ProfessionalController@dashboard', ['ProfessionalMiddleware']);
$router->get('professional/projects', 'ProfessionalController@projects', ['ProfessionalMiddleware']);
$router->get('professional/projects/{id}', 'ProfessionalController@viewProject', ['ProfessionalMiddleware']);
$router->post('professional/projects/{id}/proposal', 'ProfessionalController@submitProposal', ['ProfessionalMiddleware']);
$router->post('professional/projects/{id}/accept', 'ProfessionalController@acceptProject', ['ProfessionalMiddleware']);
$router->get('professional/proposals', 'ProfessionalController@myProposals', ['ProfessionalMiddleware']);
$router->get('professional/contracts', 'ProfessionalController@myContracts', ['ProfessionalMiddleware']);
$router->get('professional/reviews', 'ProfessionalController@reviews', ['ProfessionalMiddleware']);
$router->post('professional/projects/{id}/proposals/{proposalId}/message', 'ProfessionalController@sendMessage', ['ProfessionalMiddleware']);

// ==========================================
// ROTAS DO ADMIN
// ==========================================

$router->get('admin/dashboard', 'AdminController@dashboard', ['AdminMiddleware']);
$router->get('admin/users', 'AdminController@users', ['AdminMiddleware']);
$router->get('admin/users/{id}', 'AdminController@showUser', ['AdminMiddleware']);
$router->post('admin/users/{id}/toggle', 'AdminController@toggleUserStatus', ['AdminMiddleware']);
$router->get('admin/payments', 'AdminController@payments', ['AdminMiddleware']);
$router->get('admin/configs', 'AdminController@configs', ['AdminMiddleware']);
$router->post('admin/configs', 'AdminController@saveConfigs', ['AdminMiddleware']);
$router->get('admin/email', 'AdminController@emailConfig', ['AdminMiddleware']);
$router->post('admin/email', 'AdminController@saveEmailConfig', ['AdminMiddleware']);
$router->post('admin/email/test', 'AdminController@testEmail', ['AdminMiddleware']);
$router->get('admin/ai-logs', 'AdminController@aiLogs', ['AdminMiddleware']);

// ==========================================
// ROTAS DE CONTA (AUTENTICADO)
// ==========================================

$router->get('account', 'AccountController@index', ['AuthMiddleware']);
$router->post('account', 'AccountController@update', ['AuthMiddleware']);
$router->post('account/address', 'AccountController@updateAddress', ['AuthMiddleware']);
$router->get('account/password', 'AccountController@changePassword', ['AuthMiddleware']);
$router->post('account/password', 'AccountController@updatePassword', ['AuthMiddleware']);

// ==========================================
// WEBHOOK
// ==========================================

$router->post('webhook/assas', 'PaymentController@webhook');
