<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a borrow request can't be satisfied at the moment it's actually
 * committed — the authoritative check happens inside a locked transaction,
 * not the earlier pre-check, so this can legitimately fire even when the
 * pre-check passed a moment earlier (another request took the last unit).
 */
class InsufficientInventoryException extends RuntimeException
{
}
