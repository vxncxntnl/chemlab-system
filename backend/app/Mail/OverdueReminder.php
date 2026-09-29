<?php

namespace App\Mail;

use App\Models\Borrowing;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OverdueReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Borrowing $borrowing, public bool $isOverdue = false) {}

    public function envelope(): Envelope
    {
        $subject = $this->isOverdue
            ? 'OVERDUE: Please Return Equipment – ' . $this->borrowing->claim_code
            : 'Reminder: Equipment Due Tomorrow – ' . $this->borrowing->claim_code;

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.overdue-reminder');
    }
}
