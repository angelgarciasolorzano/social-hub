<?php

declare(strict_types=1);

namespace App\Modules\Admin\Mail;

use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

final class AdminInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $plainToken,
        private readonly CarbonImmutable $expiresAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'You have been invited to Social Hub Admin');
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'admin::Email.adminInvitation',
            with: [
                'acceptUrl' => route('admin.invitations.accept', ['token' => $this->plainToken]),
                'expiresAt' => $this->expiresAt,
            ],
        );
    }
}
