<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an action tries to transition a Borrowing from a status it's
 * no longer actually in — e.g. returning something already returned, or
 * approving something already approved. The controller-level status checks
 * that exist before calling into the service are a UX nicety only; this
 * exception is the real guard, raised after re-reading the row under a lock
 * inside the transaction, so a double-click or two concurrent requests can't
 * both succeed.
 */
class InvalidBorrowingStateException extends RuntimeException
{
}
