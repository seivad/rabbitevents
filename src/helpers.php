<?php

use Seivad\Events\MessageFactory;
use Seivad\Events\BroadcastFactory;

if (!function_exists('fire')) {

    /**
     * @param string $event
     * @param array $payload
     */
    function fire(string $event, array $payload)
    {
        app(BroadcastFactory::class)->send(
            app(MessageFactory::class)->make($event, $payload)
        );
    }

}
