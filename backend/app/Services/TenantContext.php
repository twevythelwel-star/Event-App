<?php

namespace App\Services;

use App\Models\Organizer;

class TenantContext
{
    private static ?Organizer $activeTenant = null;
    private static bool $bypassScope = false;

    /**
     * Get the active organizer model.
     */
    public static function get(): ?Organizer
    {
        return self::$activeTenant;
    }

    /**
     * Get the active tenant UUID string.
     */
    public static function id(): ?string
    {
        return self::$activeTenant?->id;
    }

    /**
     * Set the active tenant for the current request lifecycle.
     */
    public static function set(?Organizer $organizer): void
    {
        self::$activeTenant = $organizer;
    }

    /**
     * Clear the tenant context.
     */
    public static function clear(): void
    {
        self::$activeTenant = null;
        self::$bypassScope = false;
    }

    /**
     * Execute a callback bypassing tenant isolation (e.g. for platform admin global rollups).
     */
    public static function withoutTenantScope(callable $callback): mixed
    {
        $previousBypass = self::$bypassScope;
        self::$bypassScope = true;

        try {
            return $callback();
        } finally {
            self::$bypassScope = $previousBypass;
        }
    }

    /**
     * Check if tenant scope is currently bypassed.
     */
    public static function isBypassed(): bool
    {
        return self::$bypassScope;
    }
}
