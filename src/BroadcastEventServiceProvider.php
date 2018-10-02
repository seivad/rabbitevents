<?php

namespace Seivad\Events;

use Interop\Amqp\AmqpTopic;
use Interop\Queue\PsrTopic;
use Interop\Queue\PsrContext;
use Seivad\Events\Console\ListenCommand;
use Seivad\Events\Facades\BroadcastEvent;
use Illuminate\Contracts\Queue\Factory as QueueFactoryContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class BroadcastEventServiceProvider extends ServiceProvider
{
    /**
     * @var array
     */
    protected $listen = [];

    /**
     * @var string
     */
    private $exchangeName = 'events';

    /**
     * Register any events for your application.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                ListenCommand::class,
            ]);

            foreach ($this->listen as $event => $listeners) {
                foreach ($listeners as $listener) {
                    BroadcastEvent::listen($event, $listener);
                }
            }
        }
    }

    public function register()
    {
        $this->registerBroadcastEvents();
        $this->registerQueueContext();
        $this->registerPsrTopic();
        $this->registerMessageFactory();
        $this->registerEventProducer();
    }

    /**
     * @return mixed
     */
    protected function registerBroadcastEvents()
    {
        $this->app->singleton('broadcast.events', function ($app) {
            return (new Dispatcher($app))
                ->setQueueResolver(function () use ($app) {
                    return $app->make(QueueFactoryContract::class);
                });
        });
    }

    protected function registerEventProducer()
    {
        $this->app->singleton(BroadcastFactory::class);
    }

    protected function registerMessageFactory()
    {
        $this->app->singleton(MessageFactory::class);
    }

    /**
     * @return mixed
     */
    protected function registerPsrTopic()
    {
        $this->app->singleton(PsrTopic::class, function ($app) {
            $context = $app->make(PsrContext::class);

            $topic = $context->createTopic($this->exchangeName);
            $topic->setType(AmqpTopic::TYPE_TOPIC);

            $context->declareTopic($topic);

            return $topic;
        });
    }

    /**
     * @return mixed
     */
    protected function registerQueueContext()
    {
        $this->app->singleton(PsrContext::class, function ($app) {
            return $app['queue']->connection()->getPsrContext();
        });
    }
}
