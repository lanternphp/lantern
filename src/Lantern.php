<?php

namespace Lantern;

use Lantern\Features\AvailabilityBuilder;
use Lantern\Features\FeatureRegistry;

class Lantern
{
    /**
     * @var string[] an array of additional directories to use when searching the
     */
    protected static $pathDirs = [];

    /**
     * @var string|null a class to use as a custom availability builder
     */
    protected static $customAvailabilityBuilder = null;

    /**
     * @param array $dirs
     * @return array
     */
    public static function pathDirs(array $dirs = []): array
    {
        if (!empty($dirs)) {
            return static::$pathDirs = $dirs;
        }

        $defaults = [
            base_path(),
            base_path('vendor/bin'),
        ];

        return array_merge(self::$pathDirs, $defaults);
    }

    /**
     * Register one or more top-level feature stacks.
     *
     * This method resets the feature registry and then registers each provided feature.
     * Each feature argument represents the top-level feature of a feature stack.
     *
     * Example:
     * ```php
     * Lantern::register(AppFeatures::class);
     * // or register multiple feature stacks
     * Lantern::register(AppFeatures::class, AdminFeatures::class);
     * ```
     *
     * @param string ...$features One or more feature class names to register
     * @throws LanternException
     */
    public static function register(string ...$features)
    {
        FeatureRegistry::reset();

        foreach ($features as $feature) {
            FeatureRegistry::register($feature);
        }
    }

    /**
     * Reset the feature registry.
     *
     * This clears all registered features and actions from the registry.
     * Optionally, you can specify a stack to reset only that stack.
     *
     * @param string|null $stack Optional stack name to reset. If null, resets all stacks.
     */
    public static function reset(?string $stack = null)
    {
        FeatureRegistry::reset($stack);
    }

    /**
     * Register a single feature group.
     *
     * @deprecated Use Lantern::register() instead. The register() method provides a cleaner API
     *             and supports registering multiple feature stacks in a single call.
     *             Example: Lantern::register(AppFeatures::class)
     *
     * @param string $group The feature class name to register
     * @throws LanternException
     */
    public static function setUp(string $group)
    {
        FeatureRegistry::register($group);
    }

    /**
     * @return string
     */
    public static function availabilityBuilder(): string
    {
        return static::$customAvailabilityBuilder ?? AvailabilityBuilder::class;
    }

    /**
     * @param string $avilabilityBuilder
     */
    public static function useCustomAvailabilityBuilder(string $avilabilityBuilder)
    {
        static::$customAvailabilityBuilder = $avilabilityBuilder;
    }
}
