<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when the exact same borrowing request (same user, same equipment,
 * same dates) is submitted again while an identical submission is still
 * being processed or was just processed — the double-click case. Backed by
 * an atomic cache lock keyed on the request's own content, not a timestamp
 * or session flag, so it can't be bypassed by firing requests in parallel.
 */
class DuplicateSubmissionException extends RuntimeException
{
}
