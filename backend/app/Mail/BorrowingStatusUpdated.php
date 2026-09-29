<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingStatusUpdated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * NOT named $message: Laravel's Mailer auto-injects a $message binding
     * into every mail view (the underlying Illuminate\Mail\Message, for
     * legacy access to things like ->embed()) — a public property on the
     * Mailable of that same name gets silently shadowed by it at actual
     * send time. Because this only fires when the view is rendered for a
     * real send (queued jobs render lazily when a worker picks them up,
     * not when queue() is called), this was invisible in normal manual
     * testing and only surfaced once a test rendered the mailable's view.
     */
    public function __construct(
        public Borrowing $borrowing,
        public string $statusLabel,
        public string $statusMessage
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Borrowing Request ' . $this->statusLabel . ' – ' . $this->borrowing->claim_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.borrowing-status-updated',
        );
    }
}
