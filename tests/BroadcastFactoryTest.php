<?php

namespace Seivad\Events\Tests;

use Enqueue\AmqpLib\AmqpContext;
use Interop\Amqp\Impl\AmqpTopic;
use Enqueue\AmqpLib\AmqpProducer;
use Interop\Amqp\Impl\AmqpMessage;
use Seivad\Events\BroadcastFactory;

class BroadcastFactoryTest extends TestCase
{
    public function testSend()
    {
        $message = new AmqpMessage('Hello!');
        $topic = new AmqpTopic('events');

        $producer = \Mockery::mock(AmqpProducer::class);
        $producer->shouldReceive('send')
            ->with($topic, $message)
            ->once();

        $context = \Mockery::mock(AmqpContext::class)->makePartial();
        $context->shouldReceive('createProducer')
            ->andReturn($producer);

        $factory = new BroadcastFactory($context, $topic);

        self::assertNull($factory->send($message));
    }
}
