<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a borrower already has 2 active (not yet returned) borrowing
 * transactions and tries to submit another one.
 */
class BorrowingLimitExceededException extends RuntimeException
{
}
