<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\VerificationCode;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AuthOtpController extends Controller
{
     // Return View of OTP Login Page
    public function login()
    {  
        return view('auth.otp-login');
    }

    // Generate OTP
    public function generate(Request $request)
    {
        
        $id = Auth::user()->id;
$currentuser = User::find($id);
$mobile= $currentuser->mobile;
//echo $mobile;exit;
 
$v= Validator::make($request->all(), [
    'mobile' => [
        'required',
        Rule::in([$mobile]),
    ],
]);

if ($v->fails()) 
{
    return redirect()->route('otp.login')->with('mobile_message', 'Mobile number entered is not correct');
}
        // # Validate Data
        // $request->validate([
        //     'mobile' => 'required|exists:users,mobile'
        // ]);

        # Generate An OTP
         $verificationCode = $this->generateOtp($request->mobile);

        $message = "Your OTP for verification is - ".$verificationCode->otp;
        
        # Return With OTP 

        return redirect()->route('otp.verification', ['user_id' => $verificationCode->user_id])->with('success',  $message); 
    }

    public function generateOtp($mobile)
    {
        $user = User::where('mobile', $mobile)->first();

        # User Does not Have Any Existing OTP
        $verificationCode = VerificationCode::where('user_id', $user->id)->latest()->first();

        $now = Carbon::now();

        if($verificationCode && $now->isBefore($verificationCode->expire_at)){
            return $verificationCode;
        }

        // Create a New OTP
        return VerificationCode::create([
            'user_id' => $user->id,
            'otp' => rand(123456, 999999),
            'expire_at' => Carbon::now()->addMinutes(10)
        ]);
    }

    public function verification($user_id)
    {
        return view('auth.otp-verification')->with([
            'user_id' => $user_id
        ]);
    }

    public function loginWithOtp(Request $request)
    {
        #Validation
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp' => 'required'
        ]);

        #Validation Logic
        $verificationCode   = VerificationCode::where('user_id', $request->user_id)->where('otp', $request->otp)->first();

        $now = Carbon::now();
        if (!$verificationCode) {
            return redirect()->back()->with('error', 'Your OTP is not correct');
        }elseif($verificationCode && $now->isAfter($verificationCode->expire_at)){
            return redirect()->route('otp.login')->with('error', 'Your OTP has been expired');
        }

        $user = User::whereId($request->user_id)->first();

        if($user){
            // Expire The OTP
            $verificationCode->update([
                'expire_at' => Carbon::now()
            ]);

            Auth::login($user);

            return redirect('/home');
        }

        return redirect()->route('otp.login')->with('error', 'Your Otp is not correct');
    }
}