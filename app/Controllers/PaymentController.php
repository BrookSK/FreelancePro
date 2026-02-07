<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\AdminConfig;
use App\Services\AssasService;

/**
 * PaymentController - Gerenciamento de Pagamentos
 */
class PaymentController extends Controller
{
    protected Payment $paymentModel;
    protected Subscription $subscriptionModel;

    public function __construct()
    {
        $this->paymentModel = new Payment();
        $this->subscriptionModel = new Subscription();
    }

    public function plans(): void
    {
        $planModel = new SubscriptionPlan();
        $plans = $planModel->getAllActiveWithFeatures();

        $this->setLayout('dashboard');
        $this->view('payments/plans', [
            'title' => 'Planos de Assinatura',
            'plans' => $plans,
        ]);
    }

    public function checkout(int $planId): void
    {
        $user = $this->currentUser();
        $planModel = new SubscriptionPlan();
        $plan = $planModel->getWithDecodedFeatures($planId);

        if (!$plan) {
            $this->flash('error', 'Plano não encontrado.');
            $this->redirect('plans');
        }

        $userModel = new User();
        $userData = $userModel->find($user['id']);

        $this->setLayout('dashboard');
        $this->view('payments/checkout', [
            'title' => 'Checkout - ' . $plan['name'],
            'plan' => $plan,
            'userData' => $userData,
            'csrf' => $this->generateCsrfToken(),
        ]);
    }

    public function processCheckout(): void
    {
        if (!$this->validateCsrf()) {
            $this->json(['error' => 'Token inválido'], 400);
        }

        $user = $this->currentUser();
        $planId = (int) $this->input('plan_id');

        $planModel = new SubscriptionPlan();
        $plan = $planModel->find($planId);

        if (!$plan) {
            $this->json(['error' => 'Plano não encontrado'], 404);
        }

        try {
            // Verificar chave do ASSAS para decidir se vamos simular ou chamar a API real
            $configModel = new AdminConfig();
            $assasApiKey = $configModel->get('assas_api_key', '');

            // Modo simulado (sem integração externa)
            if (empty($assasApiKey)) {
                $this->json([
                    'error' => 'Pagamento indisponível. Configuração ASSAS ausente.',
                ], 400);
            }

            // Modo real: integração com ASSAS
            $assas = new AssasService();

            // Criar/atualizar cliente no ASSAS
            $customerData = [
                'name' => $this->input('name'),
                'email' => $this->input('email'),
                'cpfCnpj' => preg_replace('/\D/', '', $this->input('cpf')),
                'phone' => preg_replace('/\D/', '', $this->input('phone')),
                'postalCode' => preg_replace('/\D/', '', $this->input('zip_code')),
                'address' => $this->input('street'),
                'addressNumber' => $this->input('number'),
                'complement' => $this->input('complement'),
                'province' => $this->input('neighborhood'),
            ];

            $customer = $assas->createCustomer($customerData);

            // Criar assinatura
            $subscriptionData = [
                'customer' => $customer['id'],
                'billingType' => 'CREDIT_CARD',
                'value' => $plan['price'],
                'cycle' => 'MONTHLY',
                'description' => 'Assinatura ' . $plan['name'],
                'creditCard' => [
                    'holderName' => $this->input('card_holder'),
                    'number' => preg_replace('/\D/', '', $this->input('card_number')),
                    'expiryMonth' => $this->input('card_month'),
                    'expiryYear' => $this->input('card_year'),
                    'ccv' => $this->input('card_cvv'),
                ],
                'creditCardHolderInfo' => [
                    'name' => $this->input('name'),
                    'email' => $this->input('email'),
                    'cpfCnpj' => preg_replace('/\D/', '', $this->input('cpf')),
                    'postalCode' => preg_replace('/\D/', '', $this->input('zip_code')),
                    'addressNumber' => $this->input('number'),
                    'phone' => preg_replace('/\D/', '', $this->input('phone')),
                ],
            ];

            $subscription = $assas->createSubscription($subscriptionData);

            // Salvar assinatura no banco
            $this->subscriptionModel->create([
                'company_id' => $user['id'],
                'plan_id' => $planId,
                'status' => 'active',
                'assas_subscription_id' => $subscription['id'],
                'assas_customer_id' => $customer['id'],
                'current_period_start' => date('Y-m-d'),
                'current_period_end' => date('Y-m-d', strtotime('+1 month')),
            ]);

            // Registrar pagamento
            $this->paymentModel->create([
                'user_id' => $user['id'],
                'type' => 'subscription',
                'amount' => $plan['price'],
                'description' => 'Assinatura ' . $plan['name'],
                'assas_payment_id' => $subscription['id'],
                'status' => 'confirmed',
                'paid_at' => date('Y-m-d H:i:s'),
            ]);

            $this->json([
                'success' => true,
                'message' => 'Assinatura realizada com sucesso!',
                'redirect' => $this->url('payment/success'),
            ]);

        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function success(): void
    {
        $this->setLayout('dashboard');
        $this->view('payments/success', ['title' => 'Pagamento Confirmado']);
    }

    public function failure(): void
    {
        $this->setLayout('dashboard');
        $this->view('payments/failure', ['title' => 'Falha no Pagamento']);
    }

    public function history(): void
    {
        $user = $this->currentUser();
        $payments = $this->paymentModel->getByUser($user['id']);

        $this->setLayout('dashboard');
        $this->view('payments/history', [
            'title' => 'Histórico de Pagamentos',
            'payments' => $payments,
        ]);
    }

    public function subscription(): void
    {
        $user = $this->currentUser();
        $subscription = $this->subscriptionModel->getActiveByCompany($user['id']);

        $this->setLayout('dashboard');
        $this->view('payments/subscription', [
            'title' => 'Minha Assinatura',
            'subscription' => $subscription,
        ]);
    }

    public function cancelSubscription(): void
    {
        $user = $this->currentUser();
        $subscription = $this->subscriptionModel->getActiveByCompany($user['id']);

        if (!$subscription) {
            $this->json(['error' => 'Assinatura não encontrada'], 404);
        }

        try {
            $configModel = new AdminConfig();
            $assasApiKey = $configModel->get('assas_api_key', '');

            // Cancelamento simulado (sem integração ASSAS)
            if (empty($assasApiKey) || substr((string)($subscription['assas_subscription_id'] ?? ''), 0, 10) === 'SIMULATED-') {
                $this->subscriptionModel->cancel($subscription['id']);
                $this->json(['success' => true, 'message' => 'Assinatura cancelada (simulação).']);
            }

            // Cancelamento real via ASSAS
            $assas = new AssasService();
            $assas->cancelSubscription($subscription['assas_subscription_id']);
            $this->subscriptionModel->cancel($subscription['id']);

            $this->json(['success' => true, 'message' => 'Assinatura cancelada.']);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    public function webhook(): void
    {
        $payload = json_decode(file_get_contents('php://input'), true);

        if (!$payload || !isset($payload['event'])) {
            http_response_code(400);
            exit;
        }

        // Processar eventos do ASSAS
        switch ($payload['event']) {
            case 'PAYMENT_CONFIRMED':
            case 'PAYMENT_RECEIVED':
                $this->handlePaymentConfirmed($payload['payment']);
                break;
            case 'PAYMENT_OVERDUE':
                $this->handlePaymentOverdue($payload['payment']);
                break;
        }

        http_response_code(200);
    }

    protected function handlePaymentConfirmed(array $payment): void
    {
        $existing = $this->paymentModel->findByAssasId($payment['id']);
        if ($existing) {
            $this->paymentModel->updateStatus($existing['id'], 'confirmed');
        }
    }

    protected function handlePaymentOverdue(array $payment): void
    {
        $existing = $this->paymentModel->findByAssasId($payment['id']);
        if ($existing) {
            $this->paymentModel->updateStatus($existing['id'], 'failed');
        }
    }
}
