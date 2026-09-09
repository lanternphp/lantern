<?php

namespace Lantern\Features;

use Illuminate\Auth\Access\Response;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;

/**
 * AvailabilityBuilder is used to declare a set of checks that must be passed before
 * an Action is deemed available.
 *
 * E.g. you might want to check:
 * - current user has a specific privilege
 * - current user was the owner of a particular resource
 *
 * This will be done, much like testing, by asserting the result of different operations that
 * you declare.
 *
 * Unlike Constraints, Availability checks are never cached.
 */
class AvailabilityBuilder
{
    protected Action $action;
    /** @var array<int, callable(): Response> */
    protected array $checks = [];

    protected AuthenticatableContract $user;

    public function __construct(Action $action, AuthenticatableContract $user)
    {
        $this->user = $user;
        $this->action = $action;
    }

    public function user()
    {
        return $this->user;
    }

    public function action()
    {
        return $this->action;
    }

    protected function gate(): GateContract
    {
        return app(GateContract::class)->forUser($this->user);
    }

    public function userCan($ability, $arguments = []): self
    {
        $this->checks[] = function () use ($ability, $arguments): Response {
            if ($this->gate()->check($ability, $arguments)) {
                return Response::allow();
            }

            return Response::deny(sprintf('User does not have access to ability: %s', $ability));
        };

        return $this;
    }

    public function userCannot($ability, $arguments = []): self
    {
        $this->checks[] = function () use ($ability, $arguments): Response {
            if (! $this->gate()->check($ability, $arguments)) {
                return Response::allow();
            }

            return Response::deny(sprintf('User has access to ability: %s', $ability));
        };

        return $this;
    }

    public function assertTrue(bool $value, $failureMessage = 'value passed to `assertTrue` is false')
    {
        $this->checks[] = function () use ($value, $failureMessage): Response {
            if ($value === true) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertFalse(bool $value, $failureMessage = 'value passed to `assertFalse` is true')
    {
        $this->checks[] = function () use ($value, $failureMessage): Response {
            if ($value === false) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertNull($value, $failureMessage = 'value passed to `assertNull` is not null')
    {
        $this->checks[] = function () use ($value, $failureMessage): Response {
            if ($value === null) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertNotNull($value, $failureMessage = 'value passed to `assertNotNull` is null')
    {
        $this->checks[] = function () use ($value, $failureMessage): Response {
            if ($value !== null) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertEmpty($value, $failureMessage = 'value passed to `assertEmpty` is not empty')
    {
        $this->checks[] = function () use ($value, $failureMessage): Response {
            if (empty($value)) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertNotEmpty($value, $failureMessage = 'value passed to `assertNotEmpty` is empty')
    {
        $this->checks[] = function () use ($value, $failureMessage): Response {
            if (!empty($value)) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertEqual($expected, $other, $failureMessage = 'values passed to `assertEqual` are not equal')
    {
        $this->checks[] = function () use ($expected, $other, $failureMessage): Response {
            if ($expected == $other) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    public function assertNotEqual($expected, $other, $failureMessage = 'values passed to `assertNotEqual` are equal')
    {
        $this->checks[] = function () use ($expected, $other, $failureMessage): Response {
            if ($expected != $other) {
                return Response::allow();
            }

            return Response::deny($failureMessage);
        };

        return $this;
    }

    /**
     * Have the declared availability checks been met.
     *
     * @return Response
     */
    public function checksMet(): Response
    {
        $notMet = collect($this->checks)
            ->map(function (callable $check) {
                /** @var Response $response */
                $response = call_user_func($check);
                if ($response->allowed()) {
                    return null;
                } else {
                    return $response->message();
                }
            })
            ->filter()
        ;

        if ($notMet->count() == 0) {
            return Response::allow(
                sprintf('Action "%s": all checks passed', $this->action::id())
            );
        }

        return Response::deny(
            sprintf('Action "%s": some checks failed. %s.', $this->action::id(), $notMet->implode(', '))
        );
    }
}