<?php

namespace App\Jobs;

use App\Mail\LatePenaltyNotification;
use App\Notifications\LatePenaltyNotification as NotificationsLatePenalty;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class sendLateSessionMail implements ShouldQueue
{
    use Queueable;
    public $session;
    public $refundAmount;
    /**
     * Create a new job instance.
     */
    public function __construct($session, $refundAmount)
    {
        $this->session = $session;
        $this->refundAmount = $refundAmount;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Mail::to($this->session['tutor_email'])->send(new LatePenaltyNotification($this->session, $this->refundAmount));
        Mail::to($this->session['student_email'])->send(new LatePenaltyNotification($this->session, $this->refundAmount, true));

        //notification
        $tutor = User::where('email', $this->session['tutor_email'])->first();
        $student = User::where('email', $this->session['student_email'])->first();

        $tutor->notify(new NotificationsLatePenalty('Late Penalty Notification', $this->session));
        $student->notify(new NotificationsLatePenalty('Late Penalty Notification', $this->session));

    }
}
