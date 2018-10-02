<?php

namespace Seivad\Events\Tests;

use Mockery as m;
use Seivad\Events\Job;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrConsumer;
use Illuminate\Container\Container;

class JobTest extends TestCase
{
    /**
     * @var string
     */
    private $connectionName = 'interop';

    /**
     * @var string
     */
    private $event = 'event.called';

    /**
     * @var mixed
     */
    private $job;

    /**
     * @var string
     */
    private $listenerClass = 'ListenerClass';

    public function setUp()
    {
        $callback = function ($event, $payload) {
            return "Event: $event. Item id: {$payload['id']}";
        };

        $message = m::mock(PsrMessage::class);
        $message->shouldReceive('getBody')
            ->andReturn('{"id": 1}');

        $this->job = new Job(
            m::mock(Container::class),
            m::mock(PsrContext::class),
            m::mock(PsrConsumer::class),
            $message,
            $this->connectionName,
            $this->event,
            $this->listenerClass,
            $callback
        );
    }

    public function testFire()
    {
        self::assertEquals("Event: $this->event. Item id: 1", $this->job->fire());
    }

    public function testGetName()
    {
        $expectedMessage = "$this->connectionName: $this->event:$this->listenerClass";

        self::assertEquals($expectedMessage, $this->job->getName());
    }
}
