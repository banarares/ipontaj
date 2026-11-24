<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['first_name' => 'required|string|max:100', 'last_name' => 'required|string|max:100', 'email' => 'required|email|max:254', 'subject' => 'required|string|max:160', 'message' => 'required|string|min:10|max:5000', 'website' => 'nullable|string|max:0']);
        unset($data['website']);
        $data['email'] = mb_strtolower($data['email']);
        $message = ContactMessage::create(['data' => $data]);
        try {
            Notification::route('mail', config('legal.operator.email'))->notify(new ContactMessageReceived($message->id));
        } catch (\Throwable $error) {
            // Keep the message durable without logging its personal contents or SMTP credentials.
            Log::warning('Contact notification could not be queued.', ['reference' => $message->id, 'error_type' => class_basename($error)]);
        }

        return back()->with('contact_reference', $message->id);
    }
}
