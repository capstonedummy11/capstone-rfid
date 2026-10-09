<?php

namespace App\Http\Controllers\Shared\RootOwnership;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreRootTransferRequest;
use App\Models\RootTransferRequest;
use App\Models\User;
use App\Services\RootTransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

// Used by roles: Root Admin for ownership actions and authenticated recipients for signed transfer links.
class RootOwnershipController extends Controller
{
    // @function store: Pinoproseso ang bagong Root Ownership record.
    // @useIn store: routes/web.php:362 (root-ownership.transfers.store)
    /**
     * @feature     Ownership Transfer and Override
     * @actor       Root Admin
     * @flow        Dito sinisimulan o kina-cancel ang Root ownership transfer at emergency override.
     * @uses        resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue; routes/admin.php, routes/web.php, routes/console.php: RootOwnershipController::store, RootOwnershipController::cancel, RootOwnershipController::accept, RootOwnershipController::cancelFromLink
     * @related     Root Admin ownership at account access revocation.
     * @disable     1) Suriin ang Ownership Transfer and Override callers, pending work, at dependent screens; Needs developer check: i-resolve muna ang pending transfer/override at queued mail bago ihinto ang root-ownership:process; huwag iwan ang accepted request na walang processor.
     * @disable     2) Magdagdag at subukan ng feature-specific server guard sa named actions; panatilihin ang shared route/method para sa ibang feature. Itago pagkatapos ang controls sa `resources/js/pages/Admin/UserManagement/components/RootOwnershipPanel.vue`.
     * @disable     3) I-check ang affected user flow, reports, pending jobs, at historical read access; huwag burahin ang existing records/files bilang bahagi ng disable.
     * @sideEffects Gumagawa/nag-a-update ng transfer/override at immutable root audit; scheduler at queued mail ang kasunod.
     * @dependsOn   Root Admin ownership at account access revocation.
     * @performance Needs developer check: sukatin ang request/provider/worker work bago at pagkatapos; UI hide lang ay walang nakumpirmang bilis na dagdag.
     * @dataImpact  Walang data deletion sa nakasaad na disable steps; mananatili ang records/files pero maaaring hindi mabuksan sa hidden UI.
     * @reEnable    1) Ibalik ang server guard/action. 2) Ibalik ang UI controls. 3) I-test ang actor access, dependencies, pending work, at historical data.
     * @editable    Needs developer check: alin sa delay/approval settings ang may Admin UI; huwag baguhin ang pending records bilang template.
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
            return Inertia::render('Shared/RootOwnership/Result/ResultPage', ['title' => 'Transfer unavailable', 'message' => 'This acceptance link is invalid, expired, or has already been used.']);
        }
        abort_unless($request->user()?->user_id === $transfer->to_user_id, 403);

        return Inertia::render('Shared/RootOwnership/Accept/AcceptPage', ['transfer' => $transfer->load('fromUser', 'toUser'), 'submitUrl' => URL()->temporarySignedRoute('root-ownership.accept.store', $transfer->expires_at, ['transfer' => $transfer->id])]);
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
            return Inertia::render('Shared/RootOwnership/Result/ResultPage', ['title' => 'Transfer unavailable', 'message' => 'This cancellation link is invalid, expired, or has already been used.']);
        }

        return Inertia::render('Shared/RootOwnership/Cancel/CancelPage', ['transfer' => $transfer->load('fromUser', 'toUser'), 'submitUrl' => URL()->temporarySignedRoute('root-ownership.cancel.store', $transfer->expires_at, ['transfer' => $transfer->id, 'token' => $request->query('token')])]);
    }

    // @function cancelFromLink: Ibinabalik ang RootOwnership/Result page at data para sa request.
    // @useIn cancelFromLink: routes/web.php:77 (root-ownership.cancel.store)
    public function cancelFromLink(Request $request, RootTransferRequest $transfer, RootTransferService $service)
    {
        $service->cancel($transfer, $request->user(), (string) $request->query('token'), $request);

        return Inertia::render('Shared/RootOwnership/Result/ResultPage', ['title' => 'Transfer cancelled', 'message' => 'The Root Admin ownership transfer has been cancelled and the link cannot be reused.']);
    }
}
