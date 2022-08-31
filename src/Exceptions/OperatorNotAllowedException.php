<?php

namespace LiveIntent\LaravelResourceSearch\Exceptions;

use Illuminate\Validation\ValidationException;

class OperatorNotAllowedException extends ValidationException
{
    /**
     * Throw a new exception.
     */
    public static function make(string $field, string $operator)
    {
        return static::withMessages([
            "The '{$field}' field is not filterable with the '{$operator}' operator.",
        ]);
    }
}
