<?php

namespace LiveIntent\LaravelResourceSearch\Concerns;

trait Aliasable
{
    /**
     * The external facing name.
     *
     * @var string
     */
    protected $name;

    /**
     * The internal facing name.
     *
     * @var string
     */
    protected $internalName;

    /**
     * Get the external facing name.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Get the internal facing name.
     */
    public function getInternalName(): string
    {
        return $this->internalName;
    }
}
