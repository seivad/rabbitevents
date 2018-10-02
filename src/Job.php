<?php

namespace Seivad\Events;

use Illuminate\Support\Arr;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrConsumer;
use Illuminate\Container\Container;

class Job extends \Enqueue\LaravelQueue\Job
{
    /**
     * @var PsrConsumer
     */
    private $event;

    /**
     * @var \Callback
     */
    private $listener;

    /**
     * @var
     */
    private $listenerName;

    /**
     * @param Container $container
     * @param PsrContext $context
     * @param PsrConsumer $consumer
     * @param PsrMessage $message
     * @param string $connectionName
     * @param string $event
     * @param $listenerName
     * @param $callback
     */
    public function __construct(
        Container $container,
        PsrContext $context,
        PsrConsumer $consumer,
        PsrMessage $message,
        string $connectionName,
        string $event,
        $listenerName,
        callable $callback
    ) {
        parent::__construct($container, $context, $consumer, $message, $connectionName);

        $this->connectionName = $connectionName;
        $this->event = $event;
        $this->listenerName = $listenerName;
        $this->listener = $callback;
    }

    /**
     * @param $exception
     */
    public function failed($exception)
    {
        //TODO To think how can we use this method
    }

    /**
     * @return mixed
     */
    public function fire()
    {
        $callback = $this->listener();

        return $callback($this->event, Arr::wrap($this->payload()));
    }

    public function getName()
    {
        return "$this->connectionName: ".$this->event.":$this->listenerName";
    }

    /**
     * @return mixed
     */
    public function listener()
    {
        return $this->listener;
    }
}
