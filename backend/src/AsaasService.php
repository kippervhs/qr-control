<?php

declare(strict_types=1);

namespace QrControl;

final class AsaasService
{
    public static function createCustomer(string $name, string $email, string $userId): array
    {
        return self::request('POST', '/customers', [
            'name' => $name,
            'email' => $email,
            'externalReference' => $userId,
            'notificationDisabled' => false,
        ]);
    }

    public static function createCheckout(string $customerId, string $userId, string $name, string $email): array
    {
        $base = Config::appUrl();
        return self::request('POST', '/checkouts', [
            'billingTypes' => ['CREDIT_CARD'],
            'chargeTypes' => ['RECURRENT'],
            'minutesToExpire' => 60,
            'externalReference' => $userId,
            'customer' => $customerId,
            'callback' => [
                'successUrl' => $base . '/subscription/success',
                'cancelUrl' => $base . '/subscription/cancelled',
                'expiredUrl' => $base . '/subscription/expired',
            ],
            'items' => [[
                'name' => 'QR Control',
                'description' => 'Assinatura mensal QR Control',
                'quantity' => 1,
                'value' => 19.90,
            ]],
            'subscription' => [
                'cycle' => 'MONTHLY',
                'nextDueDate' => gmdate('Y-m-d'),
            ],
        ]);
    }

    public static function getCheckout(string $checkoutId): array
    {
        return self::request('GET', '/checkouts/' . rawurlencode($checkoutId));
    }

    public static function getSubscription(string $subscriptionId): array
    {
        return self::request('GET', '/subscriptions/' . rawurlencode($subscriptionId));
    }

    public static function listWebhooks(): array
    {
        return self::request('GET', '/webhooks?limit=100');
    }

    private static function request(string $method, string $path, ?array $payload = null): array
    {
        $url = rtrim(Config::get('ASAAS_API_URL'), '/') . $path;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: QR-Control/1.0',
            'access_token: ' . Config::get('ASAAS_API_KEY'),
        ];

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $payload === null ? '' : json_encode($payload, JSON_THROW_ON_ERROR),
                'timeout' => 30,
                'ignore_errors' => true,
                'follow_location' => 0,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => (string) parse_url($url, PHP_URL_HOST),
            ],
        ]);

        $raw = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                $status = (int) $match[1];
            }
        }

        if ($raw === false) throw new \RuntimeException('Não foi possível comunicar com o Asaas.');
        $data = json_decode($raw, true);
        if (!is_array($data)) throw new \RuntimeException('Resposta inválida recebida do Asaas.');

        if ($status < 200 || $status >= 300) {
            $description = 'Erro na comunicação com o Asaas.';
            if (isset($data['errors'][0]['description']) && is_string($data['errors'][0]['description'])) {
                $description = $data['errors'][0]['description'];
            }
            error_log(sprintf('Asaas API error: HTTP %d path=%s description=%s', $status, $path, $description));
            throw new HttpException(502, 'Não foi possível processar o pagamento. Tente novamente.');
        }

        return $data;
    }
}
