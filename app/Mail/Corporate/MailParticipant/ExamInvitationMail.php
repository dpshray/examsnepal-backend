<?php

namespace App\Mail\Corporate\MailParticipant;

use App\Models\Corporate\CorporateExam;
use App\Models\Participant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    public $exam;
    public $participant;
    public $corporate;
    /**
     * Create a new message instance.
     */
    public function __construct(CorporateExam $exam, Participant $participant, $corporate)
    {
        //
        $this->exam = $exam;
        $this->participant = $participant;
        $this->corporate = $corporate;
    }
    /**
     * Build the message.
     */
    public function build()
    {
        $examUrl = url("/exams/{$this->exam->slug}");
        $examTitle = $this->exam->title;

        return $this->subject("Invitation: {$examTitle}")
            ->view('emails.exam-invitation')
            ->with([
                'examTitle' => $this->exam->title,
                'examDescription' => $this->exam->description,
                'participantName' => $this->participant->name,
                'corporateName' => $this->corporate->name,
                'examUrl' => $examUrl,
                'startDate' => $this->exam->start_date,
                'endDate' => $this->exam->end_date,
                'loginEmail' => $this->participant->email,
                'loginPhone' => $this->participant->phone,
                'loginPassword' => $this->participant->password,
            ]);
    }
}
