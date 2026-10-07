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
    // @function store: Pinoproseso ang bagong Root Ownership record.
    // @useIn store: routes/web.php:362 (root-ownership.transfers.store)
    /**
     * @feature   Ownership Transfer and Override
     * @actor     Root Admin
     * @flow      Dito sinisimulan o kina-cancel ang Root ownership transfer at emergency override.
     * @uses      resources/js/components/Admin/RootOwnershipPanel.vue; routes/web.php: RootOwnershipController::store, RootOwnershipController::cancel, RootOwnershipController::accept, RootOwnershipController::cancelFromLink
     * @related   Root Admin workspace
     * @disable   1) I-comment out ang routes/web.php: RootOwnershipController::store/cancel/acceptShow/accept/cancelShow/cancelFromLink at RootOverrideController::store/decide.
     * @disable   2) Itago ang action sa resources/js/components/Admin/RootOwnershipPanel.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Alisin ang routes/console.php: root-ownership:process schedule.
     * @disable   4) Ihinto ang app/Http/Controllers/RootOwnershipController.php: store at app/Http/Controllers/RootOverrideController.php: store matapos alisin ang routes. Side effect: mananatili ang dating Root owner.
     */
    public function store(StoreRootTransferRequest $request, RootTransferService $service)
    {
        $target = User::query()->findOrFail($request->integer('to_user_id'));
        $service->request($request->user(), $target, $request);

        return back()->with('success', 'Root Admin transfer requested. The selected owner must accept before the 14-day effective date.');
    }

    // @function cancel: Kina-cancel ang root ownership sa Root Ownership flow.
    // @useIn cancel: routes/web.php:364 (root-ownership.transfers.cancel)
    public function cancel(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $request->user()->can('manage-root-ownership') || abort(403);
        $service->cancel($transfer, $request->user(), null, $request);

        return back()->with('success', 'Root Admin transfer cancelled.');
    }

    // @function acceptShow: Ibinabalik ang RootOwnership/Result page at data para sa request.
    // @useIn acceptShow: routes/web.php:70 (root-ownership.accept.show)
    public function acceptShow(Request $request, RootTransferRequest $transfer)
    {
        if (! $request->hasValidSignature() || $transfer->status !== 'pending' || now()->gt($transfer->expires_at)) {
            return Inertia::render('RootOwnership/Result', ['title' => 'Transfer unavailable', 'message' => 'This acceptance link is invalid, expired, or has already been used.']);
        }
        abort_unless($request->user()?->user_id === $transfer->to_user_id, 403);

        return Inertia::render('RootOwnership/Accept', ['transfer' => $transfer->load('fromUser', 'toUser'), 'submitUrl' => URL()->temporarySignedRoute('root-ownership.accept.store', $transfer->expires_at, ['transfer' => $transfer->id])]);
    }

    // @function accept: Kinukuha ang accept result para sa Root Ownership.
    // @useIn accept: routes/web.php:72 (root-ownership.accept.store)
    public function accept(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $service->accept($transfer, $request->user(), $request);

        return redirect()->route('admin.users.index')->with('success', 'Ownership transfer accepted. It will complete on the effective date.');
    }

    // @function cancelShow: Ibinabalik ang RootOwnership/Result page at data para sa request.
    // @useIn cancelShow: routes/web.php:75 (root-ownership.cancel.show)
    public function cancelShow(Request $request, RootTransferRequest $transfer)
    {
        $token = (string) $request->query('token');
        $validToken = $token !== '' && hash_equals((string) $transfer->cancel_token_hash, hash('sha256', $token));
        if (! $request->hasValidSignature() || ! $validToken || ! in_array($transfer->status, ['pending', 'accepted'], true)) {
            return Inertia::render('RootOwnership/Result', ['title' => 'Transfer unavailable', 'message' => 'This cancellation link is invalid, expired, or has already been used.']);
        }

        return Inertia::render('RootOwnership/Cancel', ['transfer' => $transfer->load('fromUser', 'toUser'), 'submitUrl' => URL()->temporarySignedRoute('root-ownership.cancel.store', $transfer->expires_at, ['transfer' => $transfer->id, 'token' => $request->query('token')])]);
    }

    // @function cancelFromLink: Ibinabalik ang RootOwnership/Result page at data para sa request.
    // @useIn cancelFromLink: routes/web.php:77 (root-ownership.cancel.store)
    public function cancelFromLink(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $service->cancel($transfer, $request->user(), (string) $request->query('token'), $request);

        return Inertia::render('RootOwnership/Result', ['title' => 'Transfer cancelled', 'message' => 'The Root Admin ownership transfer has been cancelled and the link cannot be reused.']);
    }
}
