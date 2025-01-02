<?php

namespace App\Console\Commands;

use App\Jobs\sendLateSessionMail;
use App\Models\Escrow;
use App\Models\Schedule;
use App\Services\PaystackService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckLateSessions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-late-sessions';

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
        DB::beginTransaction();
        try{
            $sessions = Schedule::where('is_started', false)
            ->where('start_time', '<', now()->subMinutes(11))
            ->where('status', '!=', 'cancel')
            ->get();

        $sessions->transform(function ($session) {
            return [
                'id' => $session->id,
                'tutor_booking_id' => $session->tutor_booking_id,
                'tutor_name' => $session->tutorBooking->tutorInfo->tutor->name,
                'tutor_email' => $session->tutorBooking->tutorInfo->tutor->email,
                'student_name' => $session->tutorBooking->student->name,
                'student_email' => $session->tutorBooking->student->email,
                'start_time' => $session->start_time,
                'duration' => Carbon::parse($session->start_time)->diffInHours(Carbon::parse($session->end_time)) . ' hours',
                'type' => $session->type,
                'session_cost' => $session->tutorBooking->session_cost,
                'referace_id' => $session->tutorBooking->escrow->reference_id,
                'zoom_link' => $session->zoom_link,
            ];
        });

        if ($sessions->isEmpty()) {
            throw new \Exception('No late session found');
        }

        foreach ($sessions as $session) {
            $lateness = now()->diffInMinutes(Carbon::parse($session['start_time']), true);

            if ($lateness > 11 && $lateness < 15) {
                //calculate penalty for late session
                $penalty = $session['session_cost'] * 0.10;


                 //check if reason type already lateness or not
                 $hasLateness = Schedule::whereJsonContains('reason', ['type' => 'lateness'])->find($session['id']);
                 if ($hasLateness) {
                    Log::info('Session is already marked as late');
                     continue;
                 }
                 //update schedule reason
                 $schedule = Schedule::find($session['id']);
                 //check if reason json column is empty
                 $reason = $schedule->reason ? json_decode($schedule->reason, true) : [];
                 $newReason = [
                     'type' => 'lateness',
                     'details' => [
                         'reason' => 'Session is late by ' . (int)$lateness . ' minutes',
                         'penalty' => $penalty,
                         'penalty_at' => now()
                     ]
                 ];
                 $reason[] = $newReason;
                 $schedule->reason = json_encode($reason);
                 $schedule->save();



                $escrow = Escrow::where('booking_id', $session['tutor_booking_id'])->first();

                $paystack = new PaystackService();
                $data = [
                    'transaction' => $escrow->reference_id,
                    'amount' => $penalty * 100,
                    'metadata' => [
                        'type' => 'penalty',
                        'message' => 'Penalty refund for late session',
                        'booking_id' => $session['tutor_booking_id'],
                        'schedule_id' => $session['id'],
                    ],
                ];

                $response = $paystack->refund($data);

                if ($response->status == false) {
                    Log::error("paystack_error: ".$response->message);
                    continue;
                }
                $escrow->deducted_amount += $penalty;
                $escrow->save();

                sendLateSessionMail::dispatch($session, $penalty);
                Log::info('Session is late by 11 minutes');

            } elseif ($lateness >= 15) {
                $refund = $session['session_cost'] - $session['session_cost'] * 0.10;
                $escrow = Escrow::where('booking_id', $session['tutor_booking_id'])->first();

                $paystack = new PaystackService();
                $data = [
                    'transaction' => $escrow->reference_id,
                    'amount' => $refund * 100,
                    'metadata' => [
                        'type' => 'refund',
                        'message' => 'Refund for late session',
                        'booking_id' => $session['tutor_booking_id'],
                        'schedule_id' => $session['id'],
                    ],
                ];
                $response = $paystack->refund($data);
                if ($response->status == false) {
                    Log::error($response->message . ' ' . $escrow->reference_id);
                    continue;
                }
                $escrow->deducted_amount += $refund;
                $escrow->status = 'refunded';
                $escrow->refund_date = now();
                $escrow->save();
                //update schedule status
                $schedule = Schedule::find($session['id']);
                //check if reason json column is empty
                $reason = $schedule->reason ? json_decode($schedule->reason, true) : [];
                $newReason = [
                    'type' => 'cancelled',
                    'details' => [
                        'cancelled_by' => 'system',
                        'cancelled_by' => now(),
                        'reason' => 'Session is cancelled due to lateness by ' . $lateness . ' minutes',
                        'comment' => 'Schedule full amount has been refunded to student for late session',
                        'refund' => $refund,
                        'refund_at' => now()
                    ]
                ];

                $reason[] = $newReason;

                $schedule->status = 'cancel';
                $schedule->reason = json_encode($reason);
                $schedule->save();
                sendLateSessionMail::dispatch($session, $refund);
               Log::info('Session is late by 15 minutes');
            }
        }

        DB::commit();
        Log::info('Command executed');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
        }
    }
}
