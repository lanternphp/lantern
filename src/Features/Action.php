<?php

namespace Lantern\Features;

use Illuminate\Auth\Access\Response;
use Illuminate\Support\Str;
use Lantern\Lantern;

/**
 * @method ActionResponse perform() – this method must be overridden with the main task of the action & must return an ActionResponse
 * @method ActionResponse prepare() – declare this method if you wish to be able to prepare data, perhaps for a view, before the action is performed
 */
abstract class Action
{
    use HasSystemConstraintsTrait;

    /**
     * Used to flag if your action is performed for guest users, not authenticated users (the default).
     *
     * @var bool Overwrite this with `true` to validate properly against guest users
     */
    const GUEST_USERS = false;

    /**
     * Used to identify the action – the ID must be unique across all Lantern actions declared.
     *
     * @var string|null Overwrite this with your own custom ID if the default is not what you want
     */
    const ID = null;

    /**
     * This is a proxy for the constructor of the static class
     *
     * @param mixed ...$dependencies
     * @return ActionProxy|static
     * @phpstan-return ActionProxy<static>
     * @see static::__construct()
     */
    public static function make(...$dependencies): ActionProxy
    {
        // @phpstan-ignore-next-line
        return new ActionProxy(new static(...$dependencies));
    }

    public static function id(): ?string
    {
        if (static::ID) {
            return static::ID;
        }

        $shortClass = collect(explode('\\', static::class))->last();

        if (Str::endsWith($shortClass, 'Action')) {
            $shortClass = Str::replaceLast('Action', '', $shortClass);
        }

        return Str::snake($shortClass, '-');
    }

    protected function success($data = null): ActionResponse
    {
        return ActionResponse::success($this, $data);
    }

    protected function failure($errors = null, array $data = []): ActionResponse
    {
        return ActionResponse::failure($this, $errors, $data);
    }

    protected function availability(AvailabilityBuilder $availabilityBuilder)
    {
    }

    /**
     * @param $user
     * @return Response
     */
    final public function checkAvailability($user): Response
    {
        if (!$user) {
            return Response::deny('User not logged in, and Action not available for GUEST_USERS');
        }

        if (!$this->constraintsMet()) {
            return Response::deny(sprintf('Action %s: constraints failed', $this::id()));
        }

        $builder = Lantern::availabilityBuilder();
        $availabilityBuilder = new $builder($this, $user);

        $this->availability($availabilityBuilder);

        return $availabilityBuilder->checksMet();
    }
}
