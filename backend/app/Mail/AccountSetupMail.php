<?php

namespace App\Mail;

use App\Models\User;
use App\Support\AccountSetupLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Welcome email for an account someone else created (e.g. a faculty member
 * adding a student), with a one-time "Set Your Password" link.
 */
class AccountSetupMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $createdBy,
        public string $url,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Set Your Password — Science Laboratory Borrowing System');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.account-setup', with: [
            'expiresInDays' => AccountSetupLink::EXPIRES_IN_DAYS,
        ]);
    }
}
