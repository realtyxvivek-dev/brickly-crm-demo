<?php

namespace App\Http\Controllers;

use App\Mail\DemoRequestMail;
use App\Models\MailDeliveryLog;
use App\Services\MailDeliveryLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class LandingPageController extends Controller
{
    public function show(): View
    {
        return view('landing');
    }

    public function storeDemoRequest(Request $request, MailDeliveryLogger $mailLogger): RedirectResponse
    {
        if ($request->filled('website')) {
            return back()->with('demo_success', 'Thanks — your request has been received.')
                ->withFragment('book-demo');
        }

        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:100'],
            'company' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+()\-\s]{7,30}$/'],
            'message' => ['nullable', 'string', 'max:1500'],
            'website' => ['nullable', 'max:0'],
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput()->withFragment('book-demo');
        }

        $data = $validator->validated();

        unset($data['website']);

        $recipient = (string) config('crm.demo_request_email');
        $subject = '['.brand_name().'] New Demo Request — '.$data['company'];
        $mailLog = $mailLogger->sendMailable(
            MailDeliveryLog::TYPE_DEMO_REQUEST,
            $subject,
            $recipient,
            new DemoRequestMail($data),
            ['source' => 'landing-preview'],
        );

        if ($mailLog->status !== MailDeliveryLog::STATUS_SENT) {
            return back()->withInput()->withErrors([
                'demo_request' => 'We could not send your request right now. Please try again in a moment.',
            ])->withFragment('book-demo');
        }

        return back()->with('demo_success', 'Thank you! Our team will contact you shortly.')
            ->withFragment('book-demo');
    }
}
