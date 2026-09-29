<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BorrowingSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Borrowing $borrowing) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New Borrowing Request – ' . $this->borrowing->claim_code,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.borrowing-submitted',
        );
    }
}
