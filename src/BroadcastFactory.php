<?php

namespace Seivad\Events;

use Interop\Queue\PsrTopic;
use Interop\Queue\PsrContext;
use Interop\Amqp\Impl\AmqpMessage;

class BroadcastFactory
{
    /**
     * @var \Enqueue\AmqpLib\AmqpProducer
     */
    private $producer;

    /**
     * @var PsrTopic
     */
    private $topic;

    /**
     * @param PsrContext $context
     * @param PsrTopic $topic
     */
    public function __construct(PsrContext $context, PsrTopic $topic)
    {
        $this->topic = $topic;
        $this->producer = $context->createProducer();
    }

    /**
     * Sends event message to queue
     *
     * @param AmqpMessage $message
     */
    public function send(AmqpMessage $message)
    {
        $this->producer->send($this->topic, $message);
    }
}
