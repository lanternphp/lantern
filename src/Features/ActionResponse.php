<?php

namespace Lantern\Features;

class ActionResponse
{
    /**
     * @var Action
     */
    protected $action;
    /**
     * @var bool
     */
    protected $success;
    /**
     * @var null
     */
    protected $data;
    /**
     * @var array
     */
    protected $errors;

    protected function __construct(Action $action, bool $success, $data = null, array $errors = [])
    {
        $this->action = $action;
        $this->success = $success;
        $this->data = $data;
        $this->errors = $errors;
    }

    // endregion ---------------------------------------------------------------\
    // region                                                 named constructors
    //--------------------------------------------------------------------------/

    public static function success(Action $action, $data = null)
    {
        return new self($action, true, $data);
    }

    public static function failure(Action $action, $errors = null, array $data = [])
    {
        return new self($action, false, $data, (array) $errors);
    }

    // endregion ---------------------------------------------------------------\
    // region                                                   instance methods
    //--------------------------------------------------------------------------/

    /**
     * Which action triggered this response?
     *
     * @return Action
     */
    public function action()
    {
        return $this->action;
    }

    public function successful()
    {
        return $this->success;
    }

    public function unsuccessful()
    {
        return ! $this->success;
    }

    /**
     * If your data is an array or an object, you can use dot-notation to access values.
     *
     * @param null|string $key
     * @param null|mixed $default value to return if using `$key`
     * @return array
     */
    public function data(?string $key = null, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * @return array of errors if present, which should be only on an unsuccessful response
     */
    public function errors(): array
    {
        return $this->errors;
    }

    // endregion
}
