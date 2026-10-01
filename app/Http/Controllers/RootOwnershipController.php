<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRootTransferRequest;
use App\Models\RootTransferRequest;
use App\Models\User;
use App\Services\RootTransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RootOwnershipController extends Controller
{
    public function store(StoreRootTransferRequest $request, RootTransferService $service)
    {
        $target = User::query()->findOrFail($request->integer('to_user_id'));
        $service->request($request->user(), $target, $request);

        return back()->with('success', 'Root Admin transfer requested. The selected owner must accept before the 14-day effective date.');
    }

    public function cancel(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $request->user()->can('manage-root-ownership') || abort(403);
        $service->cancel($transfer, $request->user(), null, $request);

        return back()->with('success', 'Root Admin transfer cancelled.');
    }

    public function acceptShow(Request $request, RootTransferRequest $transfer)
    {
        if (! $request->hasValidSignature() || $transfer->status !== 'pending' || now()->gt($transfer->expires_at)) {
            return Inertia::render('RootOwnership/Result', ['title' => 'Transfer unavailable', 'message' => 'This acceptance link is invalid, expired, or has already been used.']);
        }
        abort_unless($request->user()?->user_id === $transfer->to_user_id, 403);

        return Inertia::render('RootOwnership/Accept', ['transfer' => $transfer->load('fromUser', 'toUser'), 'submitUrl' => URL()->temporarySignedRoute('root-ownership.accept.store', $transfer->expires_at, ['transfer' => $transfer->id])]);
    }

    public function accept(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $service->accept($transfer, $request->user(), $request);

        return redirect()->route('admin.users.index')->with('success', 'Ownership transfer accepted. It will complete on the effective date.');
    }

    public function cancelShow(Request $request, RootTransferRequest $transfer)
    {
        $token = (string) $request->query('token');
        $validToken = $token !== '' && hash_equals((string) $transfer->cancel_token_hash, hash('sha256', $token));
        if (! $request->hasValidSignature() || ! $validToken || ! in_array($transfer->status, ['pending', 'accepted'], true)) {
            return Inertia::render('RootOwnership/Result', ['title' => 'Transfer unavailable', 'message' => 'This cancellation link is invalid, expired, or has already been used.']);
        }

        return Inertia::render('RootOwnership/Cancel', ['transfer' => $transfer->load('fromUser', 'toUser'), 'submitUrl' => URL()->temporarySignedRoute('root-ownership.cancel.store', $transfer->expires_at, ['transfer' => $transfer->id, 'token' => $request->query('token')])]);
    }

    public function cancelFromLink(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $service->cancel($transfer, $request->user(), (string) $request->query('token'), $request);

        return Inertia::render('RootOwnership/Result', ['title' => 'Transfer cancelled', 'message' => 'The Root Admin ownership transfer has been cancelled and the link cannot be reused.']);
    }
}
