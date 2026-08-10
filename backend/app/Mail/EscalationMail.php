<?php
namespace App\Mail;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class EscalationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $clientName,
        public string $projetName,
        public string $question,
        public string $conversationUrl,
        public string $conversationUuid,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "⚠️ Question sans réponse — {$this->projetName}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.escalation');
    }
}
