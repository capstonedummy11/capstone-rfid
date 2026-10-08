<?php
// FEATURE:root-ownership - konektadong model, service, route, o UI para sa feature na ito.

namespace App\Http\Controllers\Shared\RootOwnership;

use App\Http\Controllers\Controller;

use App\Http\Requests\StoreRootOverrideRequest;
use App\Models\RootOverrideRequest;
use App\Models\User;
use App\Services\RootOverrideService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Used by roles: Admin for override requests and decisions; Root Admin authority is checked by the controller.
class RootOverrideController extends Controller
{
    // @function store: Pinoproseso ang bagong Root Override record.
    // @useIn store: routes/web.php:366 (root-ownership.overrides.store)
    public function store(StoreRootOverrideRequest $request, RootOverrideService $service)
    {
        $service->request($request->user(), User::query()->findOrFail($request->integer('to_user_id')), $request->string('reason')->toString(), $request);

        return back()->with('success', 'Emergency override requested. Two independent approvals and the configured safety delay are required.');
    }

    // @function decide: Kinukuha ang decide result para sa Root Override.
    // @useIn decide: routes/web.php:368 (root-ownership.overrides.decide)
    public function decide(Request $request, RootOverrideRequest $override, RootOverrideService $service)
    {
        $request->user()->can('approve-root-override') || abort(403);
        $validated = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'comment' => ['nullable', 'string', 'max:2000']]);
        $service->decide($override, $request->user(), $validated['decision'], $validated['comment'] ?? null, $request);

        return back()->with('success', 'Emergency override decision recorded.');
    }
}
