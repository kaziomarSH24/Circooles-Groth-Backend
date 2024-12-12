<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Mail\sendOtp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Types\Relations\Car;
use Laravel\Socialite\Facades\Socialite;
use Tymon\JWTAuth\Contracts\Providers\JWT;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{

    /**
     * Get User
     */
    public function getUser(){
        $user = Auth::User();
        $user = collect([$user])->transform(function($data){
            return [
                'id' => $data->id,
                'name' => $data->name,
                'email' => $data->email,
                'phone' => $data->phone,
                'role' => $data->role,
                'paystack_customer_id' => $data->paystack_customer_id,
                'edu_level' => $data->edu_level,
                'remember_token' => $data->remember_token,
                'created_at' => $data->created_at,
                'updated_at' => $data->updated_at,
            ];
        });
        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * Register a User
     */

    public function register(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'phone' => 'nullable|string',
            'avatar' => 'nullable|string',
            'paystack_customer_id' => 'nullable|string',
            'role' => $request->has('edu_level') ? 'required|string' : 'nullable|string',
            'edu_level' => $request->role == 'tutor' ? 'required|string' : 'nullable|string',
            'password' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors());
        }


        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        //send otp
        $data = sendOtp($data,5);
        $user = new User();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role ?? 'user';
        $user->edu_level = $request->edu_level ?? null;
        $user->password = Hash::make($request->password);
        $user->otp = $data['otp'];
        $user->otp_expiry_at = $data['otp_expiry_at'];
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User created successfully! Please check your email for OTP!',
        ]);
    }

    /**
     * Login a User
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email|',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        if (!$token = JWTAuth::attempt($validator->validated())) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password!'
            ]);
        } else {
            $user = Auth::user();
            if ($user->email_verified_at == null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please verify your email address!',
                ]);
            }
        }

        return $this->responseWithToken($token);
    }

    /**
     * Verify account
     */
    public function verifyEmail(Request $request)
    {
        if (isset($request->otp)) {
            $user = User::where('otp', $request->otp)->first();
            if ($user) {
                if ($user->otp_expiry_at < Carbon::now()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP has expired! Please request for a new OTP!',
                    ], 401);
                }
                $user->email_verified_at = Carbon::now();
                $user->otp = null;
                $user->otp_expiry_at = null;
                $user->save();

                $token = JWTAuth::fromUser($user);

                return response()->json([
                    'success' => true,
                    'message' => 'Email verified successfully!',
                    'token' => $token,
                    'user' => $user,
                ]);
            }else{
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP!',
                ], 401);}
        } else {
            return response()->json([
                'success' => false,
                'message' => 'OTP is required!',
            ], 401);
        }
    }


    /**
     * Logout a User
     */
    public function logout(){
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            return response()->json([
                'success' => true,
                'message' => 'User logged out successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
            ]);
        }
    }


    /**
     * Response with Token
     */
    public function responseWithToken($token)
    {
        return response()->json([
            'success' => true,
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => JWTAuth::factory()->getTTL() * 600000000,
            'user' => Auth::user(),
        ]);
    }


    /**
     * resend OTP // also using for forgot password
     */
    public function resendOtp(Request $request){
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $data = [
                'name' => $user->name,
                'email' => $user->email,
            ];
            $data = sendOtp($data, 3);
            $user->otp = $data['otp'];
            $user->otp_expiry_at = $data['otp_expiry_at'];
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'OTP sent successfully! Please check your email!',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'User not found!',
            ]);
        }
    }


    /**
     * reset password
     */
     public function resetPassword(Request $request){

        if (isset($request->otp)) {
            $user = User::where('otp', $request->otp)->first();
            if($user){
                if ($user->otp_expiry_at < Carbon::now()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'OTP has expired! Please request for a new OTP!',
                    ], 401);
                }
                $validator = Validator::make($request->all(), [
                    'password' => 'required|string|confirmed|min:6',
                ]);
                if ($validator->fails()) {
                    return response()->json($validator->errors());
                }
                $user->password = Hash::make($request->password);
                $user->otp = null;
                $user->otp_expiry_at = null;
                $user->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Password reset successfully!',
                ]);
            }else{
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP!',
                ], 401);
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'OTP is required!',
            ], 401);
        }
     }

    /**
     * Update password
     */
    public function updatePassword(Request $request){
        $validator = Validator::make($request->all(), [
            'old_password' => 'required|string',
            'password' => 'required|string|confirmed|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }

        $user = Auth::user();
        if (Hash::check($request->old_password, $user->password)) {
            $user->password = Hash::make($request->password);
            $user->save();
            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully!',
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid old password!',
            ]);
        }

    }

    /**
     * Update Profile
     */
    public function updateProfile(Request $request){
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users,email,'.Auth::id(),
            'phone' => 'nullable|string',
            'avatar' => 'required|image|mimes:jpeg,png,jpg| max:2048',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors());
        }


        $user = Auth::user();
        $user->name = $request->name;
        $user->email = $request->email;
        $user->phone = $request->phone;

        if($request->hasFile('avatar')){

            if($user->avatar){
                unlink(public_path('avatars/'.$user->avatar)); //delete old image
            }

            $avatar = $request->file('avatar');
            $avatar_name = time().'.'.$avatar->extension();
            if(!file_exists(public_path('avatars'))){
                mkdir(public_path('avatars'), 0777, true); //create directory if not exists
            }
            $avatar->move(public_path('avatars'), $avatar_name);
            $user->avatar = $avatar_name;
        }
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully!',
        ]);

    }


    //social login with google
    public function loginWithGoogle(Request $request){
      return Socialite::driver('google')->stateless()->redirect();
    }

    //social login callback
    public function googleLoginCallback(){
        try {
            $user = Socialite::driver('google')->stateless()->user();
            $is_user = User::where('email', $user->getEmail())->first();

            if ($is_user) {
                $token = JWTAuth::fromUser($is_user);
                return response()->json([
                    'success' => true,
                    'message' => 'User logged in successfully!',
                    'token' => $token,
                    'user' => $is_user,
                ]);
            }
            $user = User::firstOrCreate([
                'email' => $user->email,
            ], [
                'name' => $user->name,
                'email' => $user->email,
                'password' => Hash::make($user->getName().'@' .$user->getId()),
                'avatar' => $user->avatar,
                'google_id' => $user->id,
            ]);

            $token = JWTAuth::fromUser($user);

            return response()->json([
                'success' => true,
                'message' => 'User created successfully!',
                'token' => $token,
                'user' => $user,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Something went wrong!',
                'error' => $e->getMessage(),
            ]);
        }
    }

}
