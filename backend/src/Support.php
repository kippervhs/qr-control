<?php

declare(strict_types=1);

namespace QrControl;

final class Support
{
    public static function jsonBody(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || strlen($raw) > 100_000) {
            throw new HttpException(413, 'Requisição muito grande.');
        }
        $data = json_decode($raw ?: 'null', true);
        if (!is_array($data)) {
            throw new HttpException(400, 'Dados inválidos.');
        }
        return $data;
    }

    public static function normalizeCode(string $value): ?string
    {
        $value = strtoupper(trim($value));
        if (preg_match('/^\d+$/', $value)) {
            $number = ltrim($value, '0');
            return $number !== '' ? $number : null;
        }
        return preg_match('/^[A-Z0-9]{3}-[A-Z0-9]{4}$/', $value) ? $value : null;
    }

    public static function formatCode(int|string $value): string
    {
        $value = strtoupper(trim((string) $value));
        if (preg_match('/^[A-Z0-9]{3}-[A-Z0-9]{4}$/', $value)) return $value;
        return str_pad($value, 4, '0', STR_PAD_LEFT);
    }

    public static function sequenceCode(string $prefix, int $number): string
    {
        if (!preg_match('/^[A-Z0-9]{3}$/', $prefix) || $number < 1 || $number > 1679615) {
            throw new \InvalidArgumentException('Código de sequência inválido.');
        }
        $digits = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $value = '';
        do {
            $value = $digits[$number % 36] . $value;
            $number = intdiv($number, 36);
        } while ($number > 0);
        return $prefix . '-' . str_pad($value, 4, '0', STR_PAD_LEFT);
    }

    public static function destinationUrl(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || strlen($value) > 2048 || !filter_var($value, FILTER_VALIDATE_URL)) {
            throw new HttpException(400, 'Use uma URL válida iniciada por https:// ou http://.');
        }
        $scheme = strtolower((string) parse_url($value, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw new HttpException(400, 'Use uma URL válida iniciada por https:// ou http://.');
        }
        return $value;
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20));
    }

    public static function ids(mixed $value): array
    {
        if (!is_array($value)) {
            throw new HttpException(400, 'Seleção inválida.');
        }
        $ids = array_values(array_unique($value));
        if (!$ids || count($ids) > 20) {
            throw new HttpException(400, 'Selecione entre 1 e 20 lotes.');
        }
        foreach ($ids as $id) {
            if (!is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $id)) {
                throw new HttpException(400, 'Identificador de lote inválido.');
            }
        }
        return $ids;
    }
}
