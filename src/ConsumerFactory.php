<?php

namespace Seivad\Events;

use Interop\Queue\PsrTopic;
use Interop\Amqp\AmqpContext;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrConsumer;
use Interop\Amqp\Impl\AmqpBind;
use Interop\Amqp\Impl\AmqpTopic;

class ConsumerFactory
{
    /**
     * @var AmqpContext
     */
    private $context;

    /**
     * @var AmqpTopic
     */
    private $topic;

    /**
     * @param PsrContext $context
     * @param PsrTopic $topic
     */
    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->context = $context;
        $this->topic = $topic;
    }

    /**
     * @param array $events
     *
     * @return PsrConsumer
     */
    public function make(array $events)
    {
        $queue = $this->context->createTemporaryQueue();

        foreach ($events as $event) {
            $this->bind($event, $queue);
        }

        return $this->context->createConsumer($queue);
    }

    /**
     * Bind queue to concrete event
     *
     * @param $event
     * @return $this
     */
    protected function bind($event, $queue)
    {
        $this->context->bind(new AmqpBind($this->topic, $queue, $event));

        return $this;
    }
}
