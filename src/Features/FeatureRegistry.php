<?php

namespace Lantern\Features;

use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Lantern\LanternException;

/**
 * The FeatureRegistry registers and processes all declared features, sub-features and declared actions.
 */
class FeatureRegistry
{
    protected static $features = [];

    /**
     * A mapping of which actions belong to which feature.
     *
     * @var array
     */
    protected static $actions = [];

    /**
     * A mapping of which actions appear .
     *
     * @var array
     */
    protected static $actionsToStackMaps = [];

    /**
     * As we register the features, we keep track of the call stack to allow us to
     * understand the parent-child relationships.
     *
     * @var array
     */
    protected static $callStack = [];

    /**
     * @throws LanternException
     */
    public static function register(string $feature, ?string $stack = null)
    {
        if (!class_exists($feature)) {
            throw LanternException::featureNotFound($feature);
        }

        if (!is_subclass_of($feature, Feature::class)) {
            throw LanternException::featureNotExtendingBase($feature);
        }

        if (empty($feature::ACTIONS) && empty($feature::FEATURES)) {
            throw LanternException::featureEmpty($feature);
        }

        // determine which stack to use
        $stack = static::getStack($stack, $feature::STACK);

        try {
            static::$callStack[] = new $feature;
            static::registerFeature($feature, $stack);
            array_pop(static::$callStack);
        } catch (LanternException $e) {
            static::reset($stack);
            throw $e;
        }
    }

    /**
     * Return the feature that has declared the given action
     *
     * @return Feature[]
     * @throws LanternException
     */
    public static function featuresForAction(Action $action): array
    {
        $actionId = $action::id();
        $actionClass = get_class($action);

        if (!array_key_exists($actionClass, static::$actionsToStackMaps)) {
            throw LanternException::actionNotDeclared($actionClass);
        }

        $stack = static::$actionsToStackMaps[$actionClass];

        if (!$feature = data_get(static::$actions, "$stack.$actionId")){
            throw LanternException::actionNotDeclared($actionClass);
        }

        $featureId = $feature::id();

        return data_get(static::$features, "$stack.$featureId");
    }

    /**
     * @throws LanternException
     */
    protected static function registerFeature(string $feature, string $stack)
    {
        $actionsBuilder = new ActionsBuilder($feature);
        $actionsBuilder->declare($feature::ACTIONS, $stack);

        static::addFeature($feature, $actionsBuilder, $stack);

        foreach ($feature::FEATURES as $feature) {
            static::register($feature, $stack);
        }
    }

    /**
     * @throws LanternException
     */
    protected static function addFeature($feature, ActionsBuilder $actionsBuilder, $stack)
    {
        $featureId = $feature::id();

        if (data_get(static::$features, "$stack.$featureId")) {
            throw LanternException::featureAlreadyDeclared($featureId);
        }

        data_set(static::$features, "$stack.$featureId", array_reverse(static::$callStack));

        if (count($actionsBuilder->actions()) == 0) {
            return;
        }

        foreach ($actionsBuilder->actions() as $actionId => $action) {
            if (data_get(static::$actions, "$stack.$actionId")) {
                throw LanternException::actionAlreadyDeclared($actionId);
            }

            data_set(static::$actions, "$stack.$actionId", $actionsBuilder->feature());
            static::$actionsToStackMaps[$action] = $stack;

            $gateActionId = static::getActionIdForGate($action);

            app(GateContract::class)->define($gateActionId, function ($user = null, ...$args) use ($action) {
                if ($args && $args[0] instanceof ActionProxy) {
                    return $args[0]->checkAvailabilityThroughGate($user);
                } else {
                    return $action::make(...$args)->checkAvailabilityThroughGate($user);
                }
            });
        }
    }

    public static function reset(?string $stack = null)
    {
        if ($stack) {
            static::$actions[$stack] = [];
            static::$features[$stack] = [];
        } else {
            static::$actions = [];
            static::$features = [];
        }

        static::$callStack = [];
    }

    /**
     * @throws LanternException
     */
    protected static function getStack(?string $startingStack = null, ?string $featureStack = null): string
    {
        // if we're inside a Feature already, then we ignore any stack that has been set
        if (!empty(static::$callStack) && $featureStack) {
            throw LanternException::subFeatureCannotDeclareStack();
        }

        if ($startingStack === null) {
            return $featureStack ?? 'default';
        }

        if ($featureStack === null) {
            return $startingStack;
        }

        return "$startingStack.$featureStack";
    }

    /**
     * Returns the action id including any necessary stack, as used by Laravel’s gates.
     *
     * @param Action|string $action
     * @return string
     * @throws LanternException
     * @internal do not use this method
     */
    public static function getActionIdForGate($action): string
    {
        $actionClass = is_object($action) ? get_class($action) : $action;
        $actionId = $action::id();

        if (!array_key_exists($actionClass, static::$actionsToStackMaps)) {
            throw LanternException::actionNotDeclared($actionClass);
        }

        $stack = static::$actionsToStackMaps[$actionClass];

        if ($stack !== 'default') {
            $gateActionId = "$stack.$actionId";
        } else {
            $gateActionId = $actionId;
        }

        return $gateActionId;
    }
}
