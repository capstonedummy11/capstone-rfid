<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRootOverrideRequest;
use App\Models\RootOverrideRequest;
use App\Models\User;
use App\Services\RootOverrideService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RootOverrideController extends Controller
{
    public function store(StoreRootOverrideRequest $request, RootOverrideService $service)
    {
        $service->request($request->user(), User::query()->findOrFail($request->integer('to_user_id')), $request->string('reason')->toString(), $request);

        return back()->with('success', 'Emergency override requested. Two independent approvals and the configured safety delay are required.');
    }

    public function decide(Request $request, RootOverrideRequest $override, RootOverrideService $service)
    {
        $request->user()->can('approve-root-override') || abort(403);
        $validated = $request->validate(['decision' => ['required', Rule::in(['approve', 'reject'])], 'comment' => ['nullable', 'string', 'max:2000']]);
        $service->decide($override, $request->user(), $validated['decision'], $validated['comment'] ?? null, $request);

        return back()->with('success', 'Emergency override decision recorded.');
    }
}
