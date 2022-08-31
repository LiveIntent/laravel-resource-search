<?php

use Illuminate\Container\Container;

if (! function_exists('bind_temporarily')) {
    /**
     * Temporarily bind something into the service container.
     *
     * @param  string  $abstract
     * @param  \Closure|string|null  $concrete
     * @return \Closure
     */
    function bind_temporarily($abstract, $concrete)
    {
        $original = Container::getInstance()->getBindings()[$abstract] ?? null;

        Container::getInstance()->bind($abstract, $concrete);

        return function () use ($original, $abstract) {
            if ($original) {
                Container::getInstance()->getBindings()[$abstract] = $original;
            }
        };
    }
}

if (! function_exists('call_if_callable')) {
    /**
     * Call a method if it exists and is callable, otherwise return the default.
     */
    function call_if_callable($object, $method, $default = null)
    {
        if (method_exists($object, $method) && is_callable([$object, 'method'])) {
            return call_user_func([$object, $method]);
        }

        return $default;
    }
}
