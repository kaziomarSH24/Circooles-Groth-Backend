<?php

namespace App\Console\Commands;

use App\Jobs\SendSessionReminder;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyBeforeSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:notify-before-session';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {

        $sessions = Schedule::where('is_started', false)
        ->whereIn('start_time', [
            now()->addMinutes(1),
            now()->addMinutes(5),
            now()->addMinutes(10)
        ])
        ->get();

    $sessions->transform(function ($session) {
        return [
            'id' => $session->id,
            'tutor_name' => $session->tutorBooking->tutorInfo->tutor->name,
            'tutor_email' => $session->tutorBooking->tutorInfo->tutor->email,
            'student_name' => $session->tutorBooking->student->name,
            'student_email' => $session->tutorBooking->student->email,
            'start_time' => $session->start_time,
            'duration' => Carbon::parse($session->start_time)->diffInHours(Carbon::parse($session->end_time)) . ' hours',
            'type' => $session->type,
            'zoom_link' => $session->zoom_link,
        ];
    });

    // return $sessions;

        foreach ($sessions as $session) {
                Log::info($session);
                Log::info(Carbon::parse($session['start_time'])->diffInMinutes(now()));
                SendSessionReminder::dispatch($session);
        }
        Log::info('Command executed');
    }
}
