<?php

namespace App\Services;

use App\Models\AdminConfig;

/**
 * AssasService - Integração com ASSAS para pagamentos
 */
class AssasService
{
    protected string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $config = new AdminConfig();
        $this->apiKey = $config->get('assas_api_key', '');
        $environment = $config->get('assas_environment', 'sandbox');
        
        $this->baseUrl = $environment === 'production' 
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';
    }

    protected function request(string $method, string $endpoint, array $data = []): array
    {
        if (empty($this->apiKey)) {
            throw new \Exception('Chave da API ASSAS não configurada.');
        }

        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'access_token: ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 60,
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'PUT') {
            $options[CURLOPT_CUSTOMREQUEST] = 'PUT';
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        } elseif ($method === 'DELETE') {
            $options[CURLOPT_CUSTOMREQUEST] = 'DELETE';
        }

        curl_setopt_array($ch, $options);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("Erro de conexão ASSAS: {$error}");
        }

        $result = json_decode($response, true);

        if ($httpCode >= 400) {
            $errorMsg = $result['errors'][0]['description'] ?? $result['message'] ?? 'Erro desconhecido';
            throw new \Exception("Erro ASSAS: {$errorMsg}");
        }

        return $result ?? [];
    }

    public function createCustomer(array $data): array
    {
        return $this->request('POST', '/customers', $data);
    }

    public function getCustomer(string $customerId): array
    {
        return $this->request('GET', "/customers/{$customerId}");
    }

    public function updateCustomer(string $customerId, array $data): array
    {
        return $this->request('PUT', "/customers/{$customerId}", $data);
    }

    public function findCustomerByEmail(string $email): ?array
    {
        $result = $this->request('GET', "/customers?email={$email}");
        return $result['data'][0] ?? null;
    }

    public function createSubscription(array $data): array
    {
        return $this->request('POST', '/subscriptions', $data);
    }

    public function getSubscription(string $subscriptionId): array
    {
        return $this->request('GET', "/subscriptions/{$subscriptionId}");
    }

    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->request('DELETE', "/subscriptions/{$subscriptionId}");
    }

    public function createPayment(array $data): array
    {
        return $this->request('POST', '/payments', $data);
    }

    public function getPayment(string $paymentId): array
    {
        return $this->request('GET', "/payments/{$paymentId}");
    }

    public function refundPayment(string $paymentId, float $value = null): array
    {
        $data = $value ? ['value' => $value] : [];
        return $this->request('POST', "/payments/{$paymentId}/refund", $data);
    }

    public function createCreditCardPayment(array $customerData, array $cardData, float $value, string $description): array
    {
        // Buscar ou criar cliente
        $customer = $this->findCustomerByEmail($customerData['email']);
        
        if (!$customer) {
            $customer = $this->createCustomer($customerData);
        }

        // Criar pagamento
        $paymentData = [
            'customer' => $customer['id'],
            'billingType' => 'CREDIT_CARD',
            'value' => $value,
            'dueDate' => date('Y-m-d'),
            'description' => $description,
            'creditCard' => [
                'holderName' => $cardData['holderName'],
                'number' => $cardData['number'],
                'expiryMonth' => $cardData['expiryMonth'],
                'expiryYear' => $cardData['expiryYear'],
                'ccv' => $cardData['ccv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $customerData['name'],
                'email' => $customerData['email'],
                'cpfCnpj' => $customerData['cpfCnpj'],
                'postalCode' => $customerData['postalCode'] ?? '',
                'addressNumber' => $customerData['addressNumber'] ?? '',
                'phone' => $customerData['phone'] ?? '',
            ],
        ];

        return $this->createPayment($paymentData);
    }

    public function createBoletoPayment(string $customerId, float $value, string $dueDate, string $description): array
    {
        return $this->createPayment([
            'customer' => $customerId,
            'billingType' => 'BOLETO',
            'value' => $value,
            'dueDate' => $dueDate,
            'description' => $description,
        ]);
    }

    public function createPixPayment(string $customerId, float $value, string $description): array
    {
        return $this->createPayment([
            'customer' => $customerId,
            'billingType' => 'PIX',
            'value' => $value,
            'dueDate' => date('Y-m-d'),
            'description' => $description,
        ]);
    }

    public function getPixQrCode(string $paymentId): array
    {
        return $this->request('GET', "/payments/{$paymentId}/pixQrCode");
    }
}
