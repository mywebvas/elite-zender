<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SmtpAccountResource;
use App\Models\SmtpAccount;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SmtpAccountController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SmtpAccount::class);

        return SmtpAccountResource::collection(
            SmtpAccount::orderBy('name')->paginate(50),
        );
    }

    public function show(string $id): SmtpAccountResource
    {
        $account = SmtpAccount::findOrFail($id);

        $this->authorize('view', $account);

        return new SmtpAccountResource($account);
    }
}
