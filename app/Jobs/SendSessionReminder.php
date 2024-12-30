<?php

namespace App\Jobs;

use App\Mail\SessionReminder;
use App\Models\User;
use App\Notifications\sessionReminderNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendSessionReminder implements ShouldQueue
{
    use Queueable;
    public $session;

    /**
     * Create a new job instance.
     */
    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        Mail::to($this->session['tutor_email'])->send(new SessionReminder($this->session, 'tutor'));
        Mail::to($this->session['student_email'])->send(new SessionReminder($this->session, 'student'));

        //notification
        $tutor = User::where('email', $this->session['tutor_email'])->first();
        $student = User::where('email', $this->session['student_email'])->first();

        $tutor->notify(new sessionReminderNotification($this->session));
        $student->notify(new sessionReminderNotification($this->session));

    }
}
