<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * One-time "Set Your Password" links for accounts created on someone's
 * behalf. The link is signed (can't be guessed or edited), expires after
 * EXPIRES_IN_DAYS, and carries a fingerprint of the account's current
 * password hash — so once the password is set (or reset another way), the
 * fingerprint no longer matches and the link stops working.
 */
class AccountSetupLink
{
    public const EXPIRES_IN_DAYS = 7;

    public static function for(User $user): string
    {
        return URL::temporarySignedRoute('account.setup', now()->addDays(self::EXPIRES_IN_DAYS), [
            'user' => $user->id,
            'h'    => self::fingerprint($user),
        ]);
    }

    /**
     * Valid = correctly signed, not expired, and the password hasn't changed
     * since the link was made (i.e. it hasn't been used yet).
     */
    public static function isValid(Request $request, User $user): bool
    {
        return $request->hasValidSignature()
            && hash_equals(self::fingerprint($user), (string) $request->query('h'));
    }

    private static function fingerprint(User $user): string
    {
        return substr(hash('sha256', $user->id . '|' . $user->password), 0, 24);
    }
}
