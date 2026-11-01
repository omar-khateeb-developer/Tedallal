<?php

namespace App\Mail;

use App\Models\ConsultationLead;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PreSaleConsultationSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ConsultationLead $lead)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚡ استمارة تقييم ما قبل البيع: {$this->lead->restaurant_name} - {$this->lead->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.consultation-lead',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

