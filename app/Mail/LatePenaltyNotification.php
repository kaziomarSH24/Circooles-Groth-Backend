<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LatePenaltyNotification extends Mailable
{
    use Queueable, SerializesModels;
    public $session;
    public $refundAmount;
    public $isStudent;
    /**
     * Create a new message instance.
     */
    public function __construct($session, $refundAmount, $isStudent = false)
    {
        $this->session = $session;
        $this->refundAmount = $refundAmount;
        $this->isStudent = $isStudent;
    }


    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Late Penalty Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'emails.late_penalty',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Build the message.
     */
    public function build(){

        return $this->markdown('emails.late_penalty')
        ->subject('Late Penalty Notification')
        ->with([
            'session' =>[
                'tutor_name' => $this->session['tutor_name'],
                'student_name' => $this->session['student_name'],
            ],
            'refundAmount' => $this->refundAmount,
            'isStudent' => $this->isStudent,
        ]);
    }
}
