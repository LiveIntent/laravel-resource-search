<?php

namespace LiveIntent\LaravelResourceSearch\Exceptions;

use Exception;

class InvalidResourceModelException extends Exception
{
    /**
     * Create a new instance.
     *
     * @param  mixed  $model
     */
    public function __construct(string $resource, $model)
    {
        parent::__construct("The Model '{$model}' defined on '{$resource}' is not a valid Model class.");
    }
}
