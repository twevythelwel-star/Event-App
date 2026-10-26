<?php

namespace App\Services;

class CredentialMaskingService
{
    /**
     * Mask API keys and secrets so they are never displayed in plaintext.
     * Preserves prefix if available (e.g., sk_live_, pm_live_) and last 4 characters.
     */
    public static function maskSecret(string $secret, int $visibleTail = 4): string
    {
        $len = strlen($secret);
        if ($len <= $visibleTail) {
            return str_repeat('•', $len);
        }

        // Detect prefix like sk_live_, pm_live_, whsec_, AC, AKIA
        $prefix = '';
        if (str_starts_with($secret, 'sk_live_')) {
            $prefix = 'sk_live_';
        } elseif (str_starts_with($secret, 'pm_live_')) {
            $prefix = 'pm_live_';
        } elseif (str_starts_with($secret, 'whsec_')) {
            $prefix = 'whsec_';
        } elseif (str_starts_with($secret, 'AC')) {
            $prefix = 'AC';
        } elseif (str_starts_with($secret, 'AKIA')) {
            $prefix = 'AKIA';
        } elseif ($len > 8) {
            $prefix = substr($secret, 0, 4);
        }

        $tail = substr($secret, -$visibleTail);
        return $prefix . '••••••••' . $tail;
    }

    /**
     * Mask customer telephone number for PII privacy.
     * e.g., "+1 (876) 555-4321" -> "+1 (876) •••-4321"
     */
    public static function maskPhoneNumber(string $phone): string
    {
        $clean = trim($phone);
        if (strlen($clean) < 7) {
            return '••••••';
        }
        $last4 = substr($clean, -4);
        $prefix = substr($clean, 0, strrpos($clean, '-') ?: 8);
        return $prefix . ' •••-' . $last4;
    }

    /**
     * Mask customer email for PII privacy.
     * e.g., "customer@islandevents.example" -> "c•••••••r@islandevents.example"
     */
    public static function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '••••••@••••••';
        }
        $name = $parts[0];
        $domain = $parts[1];

        if (strlen($name) <= 2) {
            $maskedName = substr($name, 0, 1) . '•';
        } else {
            $maskedName = substr($name, 0, 1) . str_repeat('•', max(3, strlen($name) - 2)) . substr($name, -1);
        }

        return $maskedName . '@' . $domain;
    }

    /**
     * Create an immutable audit log entry for privileged access to sensitive records or configuration.
     */
    public static function logPrivilegedAccess(
        string $actorId,
        string $actorRole,
        string $action,
        string $targetEntity,
        string $targetId,
        string $ipAddress = '127.0.0.1',
        string $details = ''
    ): array {
        return [
            'id' => 'sec-' . bin2hex(random_bytes(6)),
            'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'actor_id' => $actorId,
            'actor_role' => $actorRole,
            'action' => $action,
            'target_entity' => $targetEntity,
            'target_id' => $targetId,
            'ip_address' => $ipAddress,
            'severity' => in_array($action, ['view_unmasked_credentials_attempt', 'modify_platform_fees', 'account_suspension']) ? 'warning' : 'info',
            'details' => $details ?: "Privileged account {$actorId} executed {$action} on {$targetEntity}:{$targetId}",
            'is_pii_access' => in_array($targetEntity, ['payment_records', 'customer_pii', 'attendee_profile']),
        ];
    }
}
