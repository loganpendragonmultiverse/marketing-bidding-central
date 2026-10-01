<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ListingLiveMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly string $loginUrl) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Marketing Bidding Central listing is live');
    }

    public function content(): Content
    {
        return new Content(text: 'mail.listing-live');
    }
}
