<?php

namespace Seivad\Events;

class ProcessingOptions
{
    /**
     * @var int
     */
    public $maxTries;

    /**
     * @var int
     */
    public $memory;

    /**
     * @var int
     */
    public $timeout;

    /**
     * @param $memory
     * @param $timeout
     * @param $maxTries
     */
    public function __construct($memory = 128, $timeout = 60, $maxTries = 0)
    {
        $this->timeout = $timeout;
        $this->memory = $memory;
        $this->maxTries = (int) $maxTries;
    }
}
