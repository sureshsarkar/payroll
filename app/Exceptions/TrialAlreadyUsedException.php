<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 2026-06-25 — thrown by the backend trial chokepoints (CoachTrialService /
 * MembershipService) when a coach tries to claim the 14-day free trial more
 * than once. Carries the user-facing message so every entry point surfaces the
 * same text.
 */
class TrialAlreadyUsedException extends RuntimeException
{
    public const USER_MESSAGE = 'Your free trial has already been used. Please purchase a membership plan to continue.';

    public function __construct(string $message = self::USER_MESSAGE)
    {
        parent::__construct($message);
    }
}
