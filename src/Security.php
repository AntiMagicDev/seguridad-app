<?php
declare(strict_types=1);

class Security
{
    public static function sanitizeEmail(string $email): string
    {
        return filter_var(trim($email), FILTER_SANITIZE_EMAIL) ?: '';
    }

    public static function validateEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function sanitizeString(string $value, int $maxLength = 1000): string
    {
        return mb_substr(trim($value), 0, $maxLength);
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function generateCsrfToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function validateCsrfToken(?string $token, string $currentToken): bool
    {
        return !empty($token) && hash_equals($currentToken, $token);
    }

    public static function redirect(string $location): never
    {
        header('Location: ' . $location);
        exit;
    }

    public static function formatPrice(float $price): string
    {
        return number_format($price, 2, '.', ',');
    }
}
