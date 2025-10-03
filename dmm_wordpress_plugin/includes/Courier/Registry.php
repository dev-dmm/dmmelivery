<?php
/**
 * Courier Provider Registry
 * 
 * @package DMM\Courier
 */

namespace DMM\Courier;

final class Registry {
    /**
     * @var array<string,Provider>
     */
    private static $map = [];

    /**
     * Register a provider
     */
    public static function register(Provider $provider): void {
        self::$map[$provider->id()] = $provider;
    }

    /**
     * Get provider by ID
     */
    public static function get(string $id): ?Provider {
        return self::$map[$id] ?? null;
    }

    /**
     * Get all providers
     * 
     * @return Provider[]
     */
    public static function all(): array {
        return array_values(self::$map);
    }

    /**
     * Get provider IDs
     * 
     * @return string[]
     */
    public static function ids(): array {
        return array_keys(self::$map);
    }

    /**
     * Check if provider exists
     */
    public static function has(string $id): bool {
        return isset(self::$map[$id]);
    }

    /**
     * Clear all providers
     */
    public static function clear(): void {
        self::$map = [];
    }
}