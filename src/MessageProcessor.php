<?php

namespace Seivad\Events;

use Exception;
use Throwable;
use Interop\Queue\PsrContext;
use Interop\Queue\PsrMessage;
use Interop\Queue\PsrConsumer;
use Illuminate\Queue\FailingJob;
use Illuminate\Events\Dispatcher;
use Illuminate\Container\Container;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Seivad\Events\Exceptions\FailedException;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Seivad\Events\Dispatcher as BroadcastEvents;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\MaxAttemptsExceededException;
use Symfony\Component\Debug\Exception\FatalThrowableError;

class MessageProcessor
{
    /**
     * @var \Seivad\Events\Dispatcher
     */
    private $broadcastEvents;

    /**
     * @var  string
     */
    private $connectionName;

    /**
     * @var Container
     */
    private $container;

    /**
     * @var PsrContext
     */
    private $context;

    /**
     * @var \Illuminate\Events\Dispatcher
     */
    private $events;

    /**
     * @var ExceptionHandler
     */
    private $exceptions;

    /**
     * @var ProcessingOptions
     */
    private $options;

    /**
     * @param Container $container
     * @param PsrContext $context
     * @param Dispatcher $events
     * @param BroadcastEvents $broadcastEvents
     * @param ProcessingOptions $options
     * @param string $connectionName
     * @param ExceptionHandler $exceptions
     */
    public function __construct(
        Container $container,
        PsrContext $context,
        Dispatcher $events,
        BroadcastEvents $broadcastEvents,
        ProcessingOptions $options,
        string $connectionName,
        ExceptionHandler $exceptions
    ) {
        $this->container = $container;
        $this->context = $context;
        $this->events = $events;
        $this->options = $options;
        $this->broadcastEvents = $broadcastEvents;
        $this->connectionName = $connectionName;
        $this->exceptions = $exceptions;
    }

    /**
     * Fire an event and call the listeners.
     *
     * @param PsrConsumer $consumer
     * @param PsrMessage $payload
     */
    public function process(PsrConsumer $consumer, PsrMessage $payload)
    {
        $jobs = $this->makeJobs($consumer, $payload);
        try {
            foreach ($jobs as $job) {
                $response = $this->processJob($job);

                // If a boolean false is returned from a listener, we will stop propagating
                // the event to any further listeners down in the chain, else we keep on
                // looping through the listeners and firing every one in our sequence.
                if ($response === false) {
                    break;
                }
            }

            $consumer->acknowledge($payload);
        } catch (Exception $e) {
            $this->exceptions->report($e);
        } catch (Throwable $e) {
            $this->exceptions->report($e = new FatalThrowableError($e));
        }
    }

    /**
     * Mark the given job as failed and raise the relevant event.
     *
     * @param  Job $job
     * @param  \Exception $e
     * @return void
     */
    protected function failJob(Job $job, $e)
    {
        FailingJob::handle($this->connectionName, $job, $e);
    }

    /**
     * Handle an exception that occurred while the job was running.
     *
     * @param  Job $job
     * @param  \Exception $exception
     * @return void
     *
     * @throws \Exception
     */
    protected function handleJobException(Job $job, $exception)
    {
        try {
            if ($exception instanceof FailedException) {
                $this->failJob($job, $exception);
            }

            // First, we will go ahead and mark the job as failed if it will exceed the maximum
            // attempts it is allowed to run the next time we process it. If so we will just
            // go ahead and mark it as failed now so we do not have to release this again.
            if (!$job->hasFailed()) {
                $this->markJobAsFailedIfWillExceedMaxAttempts(
                    $job,
                    $this->options->maxTries,
                    $exception
                );
            }

            $this->raiseExceptionOccurredEvent($job, $exception);
        } finally {
            // If we catch an exception, we will attempt to release the job back onto the queue
            // so it is not lost entirely. This'll let the job be retried at a later time by
            // another listener (or this same one). We will re-throw this exception after.
            if (!$job->isDeleted() && !$job->isReleased() && !$job->hasFailed()) {
                $job->release();
            }
        }

        throw $exception;
    }

    /**
     * Build array of Listeners
     *
     * @param PsrConsumer $consumer
     * @param PsrMessage $payload
     * @return Job[]
     */
    protected function makeJobs(PsrConsumer $consumer, PsrMessage $payload)
    {
        $event = $payload->getRoutingKey();

        foreach ($this->broadcastEvents->getListeners($event) as $name => $listeners) {
            foreach ($listeners as $listener) {
                yield new Job(
                    $this->container,
                    $this->context,
                    $consumer,
                    $payload,
                    $this->connectionName,
                    $event,
                    $name,
                    $listener
                );
            }
        }
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * This will likely be because the job previously exceeded a timeout.
     *
     * @param  Job $job
     * @param  int $maxTries
     * @return void
     *
     * @throws \Exception
     */
    protected function markJobAsFailedIfAlreadyExceedsMaxAttempts(Job $job, $maxTries)
    {
        if ($maxTries === 0 || $job->attempts() <= $maxTries) {
            return;
        }

        $this->failJob($job, $e = new MaxAttemptsExceededException(
            'A queued job has been attempted too many times or run too long. The job may have previously timed out.'
        ));

        throw $e;
    }

    /**
     * Mark the given job as failed if it has exceeded the maximum allowed attempts.
     *
     * @param  Job $job
     * @param  int $maxTries
     * @param  \Exception $exception
     * @return void
     */
    protected function markJobAsFailedIfWillExceedMaxAttempts(Job $job, $maxTries, $exception)
    {
        if ($maxTries > 0 && $job->attempts() >= $maxTries) {
            $this->failJob($job, $exception);
        }
    }

    /**
     * Process concrete listener
     *
     * @param Job $job
     * @return array|null
     * @throws \Exception
     */
    protected function processJob(Job $job)
    {
        try {
            $this->raiseBeforeEvent($job);

            $this->markJobAsFailedIfAlreadyExceedsMaxAttempts($job, $this->options->maxTries);

            $response = $job->fire();

            $this->raiseAfterEvent($job);

            return $response;
        } catch (Exception $e) {
            $this->handleJobException($job, $e);
        } catch (Throwable $e) {
            $this->handleJobException($job, new FatalThrowableError($e));
        }
    }

    /**
     * Raise the after queue job event.
     *
     * @param  Job $job
     * @return void
     */
    protected function raiseAfterEvent(Job $job)
    {
        $this->events->dispatch(new JobProcessed($this->connectionName, $job));
    }

    /**
     * Raise the before queue job event.
     *
     * @param  Job $job
     * @return void
     */
    protected function raiseBeforeEvent(Job $job)
    {
        $this->events->dispatch(new JobProcessing($this->connectionName, $job));
    }

    /**
     * Raise the exception occurred queue job event.
     *
     * @param  Job $job
     * @param  \Exception $exception
     * @return void
     */
    protected function raiseExceptionOccurredEvent(Job $job, $exception)
    {
        $this->events->dispatch(new JobExceptionOccurred($this->connectionName, $job, $exception));
    }

    /**
     * Raise the failed queue job event.
     *
     * @param  Job $job
     * @param  \Exception $exception
     * @return void
     */
    protected function raiseFailedJobEvent(Job $job, $exception)
    {
        $this->events->dispatch(new JobFailed($this->connectionName, $job, $exception));
    }
}
