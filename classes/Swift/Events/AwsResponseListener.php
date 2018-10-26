<?php

class Swift_Events_AwsResponseListener implements Swift_Events_ResponseListener {

    private $callback;

    public function __construct(callable $callback) {
        $this->callback = $callback;
    }

    public function responseReceived(Swift_Events_ResponseEvent $event) {
        $callback = $this->callback;
        $callback($event->getResponse());
    }

}
