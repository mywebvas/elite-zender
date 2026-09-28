<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Contact;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    /**
     * Handle the unsubscribe request.
     */
    public function __invoke(Request $request, Campaign $campaign, Contact $contact)
    {
        // Handle RFC 8058 One-Click Unsubscribe (POST request)
        if ($request->isMethod('post')) {
            $contact->update(['status' => 'unsubscribed']);
            return response()->json(['message' => 'Unsubscribed successfully.']);
        }

        // Handle standard GET request (User clicks the link in the email body)
        if ($request->isMethod('get')) {
            $contact->update(['status' => 'unsubscribed']);
            return response("<h1>You have been unsubscribed.</h1><p>You will no longer receive emails from this list.</p>");
        }

        return abort(405);
    }
}
