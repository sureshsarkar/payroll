<?php

namespace App\Exceptions;

/**
 * Thrown when a coach tries to start a second live meeting while one is already
 * active. Carries the exact business message for the UI/API.
 */
class ActiveMeetingExistsException extends \Exception
{
    public function __construct(
        string $message = 'You already have a live class in progress. Please end the current session before starting a new one.'
    ) {
        parent::__construct($message);
    }
}
