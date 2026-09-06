<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;

class PasswordResetController extends Controller
{
    private const OTP_EXPIRY_MINUTES = 10;
    private const MAX_VERIFY_ATTEMPTS = 5;
    private const MAX_RESEND_ATTEMPTS = 3;
    private const RESEND_COOLDOWN_SECONDS = 90;

    // Step 1: Show "Forgot Password" form (email input)
    public function showForgotForm()
    {
        return view('auth.forgot-password');
    }

    // Step 2: Send OTP to email
    public function sendOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No account found with this email address.',
        ]);

        $email = strtolower(trim((string) $request->email));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        // Delete any old OTPs for this email
        DB::table('password_reset_otps')->where('email', $email)->delete();

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(64);

        DB::table('password_reset_otps')->insert([
            'email'       => $email,
            'otp'         => null,
            'otp_hash'    => Hash::make($otp),
            'token'       => $token,
            'is_verified' => false,
            'attempt_count' => 0,
            'resend_count' => 0,
            'last_sent_at' => Carbon::now(),
            'status'      => 'pending',
            'expires_at'  => Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        // Send OTP email
        Mail::to($request->email)->send(new PasswordResetOtpMail($otp, $user->name));

        return redirect()->route('password.otp.form', ['email' => $email])
            ->with('success', 'OTP sent to your email. Please check your inbox.');
    }

    // Step 3: Show OTP verification form
    public function showOtpForm(Request $request)
    {
        $email = $request->query('email');
        if (!$email) {
            return redirect()->route('password.forgot');
        }
        return view('auth.verify-otp', compact('email'));
    }

    // Step 4: Verify OTP
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp'   => 'required|digits:6',
        ]);

        $email = strtolower(trim((string) $request->email));
        $record = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('status', 'pending')
            ->where('is_verified', false)
            ->orderByDesc('id')
            ->first();

        if (!$record) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP. Please request a new one.'])->withInput();
        }

        if (Carbon::now()->greaterThan($record->expires_at)) {
            DB::table('password_reset_otps')->where('id', $record->id)->update([
                'status' => 'expired',
                'updated_at' => Carbon::now(),
            ]);
            return back()->withErrors(['otp' => 'OTP has expired. Please request a new one.'])->withInput();
        }

        if ((int) ($record->attempt_count ?? 0) >= self::MAX_VERIFY_ATTEMPTS) {
            DB::table('password_reset_otps')->where('id', $record->id)->update([
                'status' => 'locked',
                'locked_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return back()->withErrors(['otp' => 'Too many wrong OTP attempts. Please request a new OTP.'])->withInput();
        }

        $hash = (string) ($record->otp_hash ?? '');
        $otpMatches = $hash !== '' && Hash::check((string) $request->otp, $hash);

        if (!$otpMatches) {
            $attemptCount = ((int) ($record->attempt_count ?? 0)) + 1;
            DB::table('password_reset_otps')->where('id', $record->id)->update([
                'attempt_count' => $attemptCount,
                'status' => $attemptCount >= self::MAX_VERIFY_ATTEMPTS ? 'locked' : 'pending',
                'locked_at' => $attemptCount >= self::MAX_VERIFY_ATTEMPTS ? Carbon::now() : null,
                'updated_at' => Carbon::now(),
            ]);

            return back()->withErrors(['otp' => 'Invalid OTP. Please try again.'])->withInput();
        }

        // Mark as verified
        DB::table('password_reset_otps')
            ->where('id', $record->id)
            ->update([
                'is_verified' => true,
                'status' => 'verified',
                'verified_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

        return redirect()->route('password.reset.form', ['token' => $record->token]);
    }

    // Step 5: Show new password form
    public function showResetForm(Request $request)
    {
        $token = $request->query('token');
        if (!$token) {
            return redirect()->route('password.forgot');
        }

        $record = DB::table('password_reset_otps')
            ->where('token', $token)
            ->where('is_verified', true)
            ->where('status', 'verified')
            ->first();

        if (!$record || Carbon::now()->greaterThan($record->expires_at)) {
            return redirect()->route('password.forgot')
                ->withErrors(['email' => 'Session expired. Please start again.']);
        }

        return view('auth.reset-password', compact('token'));
    }

    // Step 6: Save new password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token'                 => 'required',
            'password'              => 'required|min:8|confirmed',
            'password_confirmation' => 'required',
        ]);

        $record = DB::table('password_reset_otps')
            ->where('token', $request->token)
            ->where('is_verified', true)
            ->where('status', 'verified')
            ->first();

        if (!$record || Carbon::now()->greaterThan($record->expires_at)) {
            return redirect()->route('password.forgot')
                ->withErrors(['email' => 'Session expired. Please start again.']);
        }

        // Update user password
        $user = User::whereRaw('LOWER(email) = ?', [strtolower((string) $record->email)])->first();
        if ($user) {
            $user->forceFill([
                'password' => Hash::make($request->password),
            ])->save();
            $user->clearPasswordChangeRequirement();
        }

        DB::table('password_reset_otps')->where('id', $record->id)->update([
            'status' => 'used',
            'used_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        return redirect()->route('login')
            ->with('success', 'Password reset successfully! Please log in with your new password.');
    }

    // Resend OTP
    public function resendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $email = strtolower(trim((string) $request->email));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        $current = DB::table('password_reset_otps')
            ->where('email', $email)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if ($current && (int) ($current->resend_count ?? 0) >= self::MAX_RESEND_ATTEMPTS) {
            DB::table('password_reset_otps')->where('id', $current->id)->update([
                'status' => 'locked',
                'locked_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
            ]);

            return back()->withErrors(['email' => 'OTP resend limit reached. Please start again after some time.']);
        }

        if ($current && $current->last_sent_at && Carbon::now()->diffInSeconds(Carbon::parse($current->last_sent_at)) < self::RESEND_COOLDOWN_SECONDS) {
            return back()->withErrors(['email' => 'Please wait before requesting another OTP.']);
        }

        DB::table('password_reset_otps')->where('email', $email)->where('status', 'pending')->update([
            'status' => 'replaced',
            'updated_at' => Carbon::now(),
        ]);

        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $token = Str::random(64);

        DB::table('password_reset_otps')->insert([
            'email'       => $email,
            'otp'         => null,
            'otp_hash'    => Hash::make($otp),
            'token'       => $token,
            'is_verified' => false,
            'attempt_count' => 0,
            'resend_count' => ((int) ($current->resend_count ?? 0)) + 1,
            'last_sent_at' => Carbon::now(),
            'status'      => 'pending',
            'expires_at'  => Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            'created_at'  => Carbon::now(),
            'updated_at'  => Carbon::now(),
        ]);

        Mail::to($request->email)->send(new PasswordResetOtpMail($otp, $user->name));

        return back()->with('success', 'New OTP sent to your email.');
    }
}
