<?php

namespace LiveIntent\LaravelResourceSearch\Exceptions;

use Exception;
use LiveIntent\LaravelResourceSearch\Directives\AllowedFilter;

class InvalidResourceFilterException extends Exception
{
    /**
     * Create a new instance.
     *
     * @param  mixed  $scope
     */
    public function __construct($scope)
    {
        $type = is_object($scope) ? $scope::class : gettype($scope);

        $allowedFilter = AllowedFilter::class;

        parent::__construct("Allowed filters must be instances of '{$allowedFilter}'. Got: '{$type}'.");
    }
}
