<?php

declare(strict_types=1);

namespace QrControl;

use PDO;

final class SubscriptionService
{
    public static function statusForCurrentUser(): array
    {
        $user = Auth::currentUser();
        if (!$user) throw new HttpException(401, 'Acesso não autorizado.');

        if ($user['role'] === 'admin') {
            return ['status' => 'ACTIVE', 'active' => true, 'currentPeriodEnd' => null, 'checkoutUrl' => null];
        }

        $row = self::row((string) $user['id']);
        $active = self::isActive($row);
        return [
            'status' => $row['status'] ?? 'PENDING',
            'active' => $active,
            'currentPeriodEnd' => $row['current_period_end'] ?? null,
            'checkoutUrl' => $active ? null : self::checkoutUrl($row),
        ];
    }

    public static function requireActive(): void
    {
        $user = Auth::currentUser();
        if (!$user) throw new HttpException(401, 'Acesso não autorizado.');
        if ($user['role'] === 'admin') return;

        $row = self::row((string) $user['id']);
        if (!self::isActive($row)) {
            throw new HttpException(402, 'Sua assinatura não está ativa.');
        }
    }

    public static function createCheckoutForCurrentUser(): array
    {
        $user = Auth::currentUser();
        if (!$user) throw new HttpException(401, 'Acesso não autorizado.');
        if ($user['role'] === 'admin') throw new HttpException(403, 'A conta administrativa não precisa de assinatura.');

        $pdo = Database::connection();
        $userId = (string) $user['id'];
        $row = self::row($userId);

        if (self::isActive($row)) {
            throw new HttpException(409, 'Sua assinatura já está ativa.');
        }

        if (!$user['email'] || !$user['full_name']) {
            throw new HttpException(409, 'Complete seus dados de cadastro antes de assinar.');
        }

        $customerId = $row['asaas_customer_id'] ?? null;
        if (!$customerId) {
            $customer = AsaasService::createCustomer((string) $user['full_name'], (string) $user['email'], $userId);
            $customerId = (string) ($customer['id'] ?? '');
            if ($customerId === '') throw new \RuntimeException('O Asaas não retornou o identificador do cliente.');
            $pdo->prepare('UPDATE subscriptions SET asaas_customer_id = ?, status = ? WHERE user_id = ?')
                ->execute([$customerId, 'PENDING', $userId]);
        }

        $checkout = AsaasService::createCheckout($customerId, $userId, (string) $user['full_name'], (string) $user['email']);
        $checkoutId = (string) ($checkout['id'] ?? '');
        if ($checkoutId === '') throw new \RuntimeException('O Asaas não retornou o identificador do checkout.');

        $pdo->prepare(
            'UPDATE subscriptions SET asaas_checkout_id = ?, status = ?, updated_at = UTC_TIMESTAMP(6) WHERE user_id = ?'
        )->execute([$checkoutId, 'PENDING', $userId]);

        return ['checkoutUrl' => self::checkoutUrl(['asaas_checkout_id' => $checkoutId])];
    }

    public static function registerWebhook(array $payload): void
    {
        $eventId = is_string($payload['id'] ?? null) ? trim($payload['id']) : '';
        $eventType = is_string($payload['event'] ?? null) ? trim($payload['event']) : '';
        if ($eventId === '' || $eventType === '') throw new HttpException(400, 'Evento inválido.');

        $pdo = Database::connection();
        $existing = $pdo->prepare('SELECT processed_at FROM asaas_webhook_events WHERE event_id = ? LIMIT 1');
        $existing->execute([$eventId]);
        $processed = $existing->fetchColumn();
        if ($processed !== false && $processed !== null) return;

        if ($processed === false) {
            $pdo->prepare('INSERT INTO asaas_webhook_events (event_id, event_type) VALUES (?, ?)')
                ->execute([$eventId, $eventType]);
        }

        try {
            $object = is_array($payload['payment'] ?? null) ? $payload['payment'] : (is_array($payload['subscription'] ?? null) ? $payload['subscription'] : (is_array($payload['checkout'] ?? null) ? $payload['checkout'] : []));
            self::processEvent($eventType, $object);
            $pdo->prepare('UPDATE asaas_webhook_events SET processed_at = UTC_TIMESTAMP(6) WHERE event_id = ?')->execute([$eventId]);
        } catch (\Throwable $error) {
            if ($processed === false) {
                $pdo->prepare('DELETE FROM asaas_webhook_events WHERE event_id = ? AND processed_at IS NULL')->execute([$eventId]);
            }
            throw $error;
        }
    }

    private static function processEvent(string $event, array $object): void
    {
        $customerId = self::extractId($object['customer'] ?? null);
        $subscriptionId = self::extractId($object['subscription'] ?? null);
        if (!$subscriptionId && isset($object['id']) && str_starts_with((string) $object['id'], 'sub_')) {
            $subscriptionId = (string) $object['id'];
        }

        if (in_array($event, ['CHECKOUT_PAID'], true)) {
            $checkoutId = (string) ($object['id'] ?? '');
            $row = self::findByCheckout($checkoutId);
            if (!$row) return;
            $subscriptionId = self::extractId($object['subscription'] ?? null);
            if ($subscriptionId === null) {
                try {
                    $checkout = AsaasService::getCheckout($checkoutId);
                    $subscriptionId = self::extractId($checkout['subscription'] ?? null);
                } catch (\Throwable) {
                    $subscriptionId = null;
                }
            }
            self::activate((string) $row['user_id'], $subscriptionId, null);
            return;
        }

        if (in_array($event, ['CHECKOUT_CANCELED', 'CHECKOUT_EXPIRED'], true)) {
            $row = self::findByCheckout((string) ($object['id'] ?? ''));
            if ($row && !self::isActive($row)) self::setStatus((string) $row['user_id'], 'PENDING', null, null);
            return;
        }

        if (in_array($event, ['SUBSCRIPTION_CREATED', 'SUBSCRIPTION_UPDATED'], true)) {
            $row = self::findByAsaasCustomerOrSubscription($customerId, $subscriptionId);
            if (!$row) return;
            $status = strtoupper((string) ($object['status'] ?? 'PENDING'));
            $nextDue = self::normalizeDate($object['nextDueDate'] ?? null);
            if ($status === 'ACTIVE') {
                self::setStatus((string) $row['user_id'], 'ACTIVE', $subscriptionId, $nextDue);
            } elseif ($status === 'INACTIVE') {
                self::setStatus((string) $row['user_id'], 'CANCELLED', $subscriptionId, null);
            }
            return;
        }

        if (in_array($event, ['SUBSCRIPTION_INACTIVATED', 'SUBSCRIPTION_DELETED'], true)) {
            $row = self::findByAsaasCustomerOrSubscription($customerId, $subscriptionId);
            if ($row) self::setStatus((string) $row['user_id'], 'CANCELLED', $subscriptionId, null);
            return;
        }

        if (in_array($event, ['PAYMENT_CONFIRMED', 'PAYMENT_RECEIVED'], true)) {
            $row = self::findByAsaasCustomerOrSubscription($customerId, $subscriptionId);
            if (!$row) return;
            $nextDue = null;
            if ($subscriptionId) {
                try {
                    $subscription = AsaasService::getSubscription($subscriptionId);
                    $nextDue = self::normalizeDate($subscription['nextDueDate'] ?? null);
                } catch (\Throwable) {
                    $nextDue = null;
                }
            }
            self::activate((string) $row['user_id'], $subscriptionId, $nextDue);
            return;
        }

        if (in_array($event, ['PAYMENT_OVERDUE', 'PAYMENT_CREDIT_CARD_CAPTURE_REFUSED', 'PAYMENT_REFUNDED', 'PAYMENT_CHARGEBACK_REQUESTED', 'PAYMENT_CHARGEBACK_DISPUTE'], true)) {
            $row = self::findByAsaasCustomerOrSubscription($customerId, $subscriptionId);
            if ($row) self::setStatus((string) $row['user_id'], 'PAST_DUE', $subscriptionId, $row['current_period_end']);
        }
    }

    private static function activate(string $userId, ?string $subscriptionId, ?string $periodEnd): void
    {
        if ($periodEnd === null && $subscriptionId !== null) {
            try {
                $subscription = AsaasService::getSubscription($subscriptionId);
                $periodEnd = self::normalizeDate($subscription['nextDueDate'] ?? null);
            } catch (\Throwable) {
                $periodEnd = null;
            }
        }

        Database::connection()->prepare(
            'UPDATE subscriptions SET status = ?, asaas_subscription_id = COALESCE(?, asaas_subscription_id), current_period_end = COALESCE(?, current_period_end), updated_at = UTC_TIMESTAMP(6) WHERE user_id = ?'
        )->execute(['ACTIVE', $subscriptionId, $periodEnd, $userId]);
    }

    private static function setStatus(string $userId, string $status, ?string $subscriptionId, ?string $periodEnd): void
    {
        Database::connection()->prepare(
            'UPDATE subscriptions SET status = ?, asaas_subscription_id = COALESCE(?, asaas_subscription_id), current_period_end = ?, updated_at = UTC_TIMESTAMP(6) WHERE user_id = ?'
        )->execute([$status, $subscriptionId, $periodEnd, $userId]);
    }

    private static function row(string $userId): ?array
    {
        $q = Database::connection()->prepare('SELECT * FROM subscriptions WHERE user_id = ? LIMIT 1');
        $q->execute([$userId]);
        $row = $q->fetch();
        return $row ?: null;
    }

    private static function findByCheckout(string $checkoutId): ?array
    {
        if ($checkoutId === '') return null;
        $q = Database::connection()->prepare('SELECT * FROM subscriptions WHERE asaas_checkout_id = ? LIMIT 1');
        $q->execute([$checkoutId]);
        $row = $q->fetch();
        return $row ?: null;
    }

    private static function findByAsaasCustomerOrSubscription(?string $customerId, ?string $subscriptionId): ?array
    {
        $q = Database::connection()->prepare('SELECT * FROM subscriptions WHERE asaas_customer_id = ? OR asaas_subscription_id = ? LIMIT 1');
        $q->execute([$customerId, $subscriptionId]);
        $row = $q->fetch();
        return $row ?: null;
    }

    private static function isActive(?array $row): bool
    {
        if (!$row || strtoupper((string) ($row['status'] ?? '')) !== 'ACTIVE') return false;
        if (!$row['current_period_end']) return true;
        return strtotime((string) $row['current_period_end'] . ' UTC') > time();
    }

    private static function checkoutUrl(?array $row): ?string
    {
        $id = $row['asaas_checkout_id'] ?? null;
        return is_string($id) && $id !== '' ? 'https://asaas.com/checkoutSession/show?id=' . rawurlencode($id) : null;
    }

    private static function extractId(mixed $value): ?string
    {
        if (is_string($value) && $value !== '') return $value;
        if (is_array($value) && is_string($value['id'] ?? null) && $value['id'] !== '') return $value['id'];
        return null;
    }

    private static function normalizeDate(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') return null;
        $timestamp = strtotime($value);
        return $timestamp === false ? null : gmdate('Y-m-d H:i:s', $timestamp);
    }
}
