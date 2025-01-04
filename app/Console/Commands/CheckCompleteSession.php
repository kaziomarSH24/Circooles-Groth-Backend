<?php

namespace App\Console\Commands;

use App\Mail\CompleteSessionMail;
use App\Models\Escrow;
use App\Models\Schedule;
use App\Models\Transaction;
use App\Models\TutorInfo;
use App\Models\User;
use App\Models\Wallets;
use App\Notifications\CompleteSessionNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class CheckCompleteSession extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-complete-session';

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
        try {
            $sessions = Schedule::where('is_started', true)
                ->where('is_completed', true)
                ->where('end_time', '<', now()->subMinutes(1))
                ->where('status', '!=', 'cancel')
                ->where('status', '!=', 'completed')
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
                    'end_time' => $session->end_time,
                    'duration' => Carbon::parse($session->start_time)->diffInHours(Carbon::parse($session->end_time)) . ' hours',
                    'type' => $session->type,
                    'is_completed' => $session->is_completed,
                    'session_cost' => $session->tutorBooking->session_cost,
                    'referace_id' => $session->tutorBooking->escrow->reference_id,
                    'zoom_link' => $session->zoom_link,
                ];
            });

            if ($sessions->isEmpty()) {
                throw new \Exception('No complete session found');
            }

            foreach ($sessions as $session) {

                Log::info($session);
                //update session status
                $schedule = Schedule::find($session['id']);
                $schedule->status = 'completed';
                $schedule->save();
                //has in completed recurrence session
                $hasIncomplete = Schedule::where('tutor_booking_id', $session['tutor_booking_id'])
                    ->where('is_completed', false)
                    ->where('status', '!=', 'cancel')
                    ->exists();

                if ($hasIncomplete > 0) {
                    Log::info('Has incomplete session');
                    continue;
                }
                //transfer money to tutor
                $tutorBooking = $session['tutor_booking_id'];
                $escrow = Escrow::where('booking_id', $tutorBooking)
                    ->where('status', 'hold')
                    ->first();

                $transferAbleAmount = $escrow->hold_amount - $escrow->deducted_amount;
                //calculate admin commission
                $adminCommission = ($transferAbleAmount * 20) / 100;
                $transferAbleAmount -= $adminCommission;

                $tutorUserID = $escrow->booking->tutorInfo->user_id;
                Log::info('Transferable amount: ' . $transferAbleAmount);
                Log::info('Tutor user id: ' . $tutorUserID);


                if ($transferAbleAmount > 0) {
                    $tutorWallet = Wallets::updateOrCreate(
                        ['user_id' => $tutorUserID],
                        ['balance' => 0]
                    );
                    $tutorWallet->balance += $transferAbleAmount;
                    // Save the updated wallet balance
                    $tutorWallet->save();
                }




                if ($adminCommission > 0) {
                    //update admin wallet
                    $adminId = User::where('role', 'admin')->first()->id;
                    $adminWallet = Wallets::updateOrCreate(
                        ['user_id' => $adminId],
                        ['balance' => 0]
                    );
                    $adminWallet->balance += $adminCommission;
                    // Save the updated wallet balance
                    $adminWallet->save();

                    //transaction history
                    $transaction = new Transaction();
                    $transaction->seller_id = $adminId;
                    $transaction->buyer_id = $tutorUserID;
                    $transaction->amount = $adminCommission;
                    $transaction->type = 'tutor_commission';
                    $transaction->save();
                }



                //update escrow
                $escrow->status = 'released';
                $escrow->release_date = now();
                $escrow->save();



                //dispatch email notification
                $data = [
                    'tutor_name' => $session['tutor_name'],
                    'tutor_email' => $session['tutor_email'],
                    'student_name' => $session['student_name'],
                    'student_email' => $session['student_email'],
                    'date' => Carbon::parse($session['start_time'])->format('d M, Y'),
                    'time' => Carbon::parse($session['start_time'])->format('h:i A'),
                    'duration' => $session['duration'],
                    'total_payment' => $transferAbleAmount,
                ];
                //send mail
                Mail::to($data['tutor_email'])->send(new CompleteSessionMail($data));
                //notification
                $tutor = User::where('email', $data['tutor_email'])->first();
                $student = User::where('email', $data['student_email'])->first();

                $tutor->notify(new CompleteSessionNotification($data, 'Session Completed'));
                $student->notify(new CompleteSessionNotification($data, 'Session Completed'));
            }
            DB::commit();
            Log::info('Transfer completed successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return;
        }
    }
}
