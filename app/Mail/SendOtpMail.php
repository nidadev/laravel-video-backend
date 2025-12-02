<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SendOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;

    public function __construct($otp)
    {
        $this->otp = $otp;
    }

    // Modern envelope (subject)
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your OTP Code'
        );
    }

    // Modern content (Blade view)
    public function content(): Content
    {
        return new Content(
            view: 'emails.send_otp' // <- Make sure this exists in resources/views/emails/send_otp.blade.php
        );
    }

    // Attachments if any
    public function attachments(): array
    {
        return [];
    }
}
