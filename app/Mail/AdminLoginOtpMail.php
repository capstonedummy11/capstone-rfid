<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AdminLoginOtpMail extends Mailable
{
    use Queueable, SerializesModels;

    // @function __construct: Tinatanggap ang dependencies ng Admin Login Otp Mail sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang AdminLoginOtpMail
    public function __construct(public readonly string $code, public readonly int $expiresMinutes) {}

    // @function envelope: Kinukuha ang envelope result para sa Admin Login Otp Mail.
    // @useIn envelope: Laravel mailable rendering at delivery
    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Admin Login Verification Code');
    }

    // @function content: Kinukuha ang content result para sa Admin Login Otp Mail.
    // @useIn content: Laravel mailable rendering at delivery
    public function content(): Content
    {
        return new Content(view: 'emails.admin-login-otp');
    }
}
