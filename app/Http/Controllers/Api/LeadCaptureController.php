<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\LeadCaptureForm;
use App\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public signup endpoint for embedded forms.
 *
 * The workspace is derived from the form's public key — never from the request
 * body. Previously the caller supplied `tenant_id` and `list_id` directly,
 * which let anyone write contacts into any workspace they could name.
 */
class LeadCaptureController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'form_key' => ['required', 'string', 'max:64'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'first_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['nullable', 'string', 'max:255'],
            // Honeypot: real users never fill a hidden field.
            'website' => ['prohibited'],
        ]);

        $form = LeadCaptureForm::withoutGlobalScopes()
            ->with('tenant')
            ->where('public_key', $validated['form_key'])
            ->where('is_active', true)
            ->first();

        if ($form === null || $form->tenant === null) {
            return response()->json([
                'error' => ['code' => 'FORM_NOT_FOUND', 'message' => 'Unknown or inactive form.'],
            ], 404);
        }

        if (! $form->allowsOrigin($request->headers->get('Origin'))) {
            return response()->json([
                'error' => ['code' => 'ORIGIN_NOT_ALLOWED', 'message' => 'This origin is not permitted to use this form.'],
            ], 403);
        }

        $contact = TenantContext::run($form->tenant, function () use ($form, $validated): Contact {
            $contact = Contact::updateOrCreate(
                ['tenant_id' => $form->tenant_id, 'email' => mb_strtolower($validated['email'])],
                array_filter([
                    'first_name' => $validated['first_name'] ?? null,
                    'last_name' => $validated['last_name'] ?? null,
                ], static fn ($value) => $value !== null),
            );

            // Never silently resurrect someone who opted out.
            if ($contact->wasRecentlyCreated) {
                $contact->forceFill(['status' => Contact::STATUS_ACTIVE])->save();
            }

            if ($form->list_id !== null) {
                $contact->lists()->syncWithoutDetaching([$form->list_id]);
            }

            // A signup form with no welcome email is a wasted first impression.
            if ($contact->wasRecentlyCreated) {
                $engine = app(\App\Automations\AutomationEngine::class);
                $engine->trigger(\App\Models\Automation::TRIGGER_SUBSCRIBED, $contact);

                if ($form->list_id !== null) {
                    $engine->trigger(\App\Models\Automation::TRIGGER_LIST_JOINED, $contact, [
                        'list_id' => $form->list_id,
                    ]);
                }
            }

            return $contact;
        });

        return response()->json([
            'data' => [
                'id' => $contact->getKey(),
                'email' => $contact->email,
                'status' => $contact->status,
            ],
        ], 201);
    }
}
