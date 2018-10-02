<?php

namespace Seivad\Events;

use Interop\Amqp\AmqpTopic;
use Interop\Queue\PsrTopic;
use Interop\Amqp\AmqpContext;
use Interop\Queue\PsrContext;

class MessageFactory
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
     * @param string $event
     * @param array $payload
     * @return \Interop\Amqp\AmqpMessage
     */
    public function make(string $event, array $payload)
    {
        $message = $this->context->createMessage(json_encode($payload));
        $message->setRoutingKey($event);

        return $message;
    }
}
