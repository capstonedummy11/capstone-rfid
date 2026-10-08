<?php

namespace App\Http\Controllers\Admin\Instructors;

use App\Models\Instructor;
use App\Models\Strand;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InstructorsController
{
    // @function index: Wala pang implementasyon ang legacy index placeholder.
    // @useIn index: TODO(verify): walang direct caller na nakita sa static search
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Display admin listing of the resource.
     */
    // @function indexAdmin: Ibinabalik ang Auth/Admin/Instructors page at data para sa request.
    // @useIn indexAdmin: routes/web.php:420 (instructors.index)
    /**
     * @feature   Instructor Management
     * @actor     Admin
     * @flow      Dito minamanage ang Instructor records at account recovery.
     * @uses      resources/js/pages/Admin/Instructors/InstructorsPage.vue; routes/web.php: InstructorsController::indexAdmin, InstructorsController::store, InstructorsController::update, InstructorsController::destroy, InstructorsController::resetPassword
     * @related   Admin workspace
     * @disable   1) I-comment out ang routes/web.php: InstructorsController::indexAdmin, InstructorsController::store, InstructorsController::update, InstructorsController::destroy, InstructorsController::resetPassword.
     * @disable   2) Itago ang action sa resources/js/pages/Admin/Instructors/InstructorsPage.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/Admin/Instructors/InstructorsController.php: InstructorsController::indexAdmin matapos alisin ang routes. Side effect: mawawala ang instructor management.
     */
    public function indexAdmin(Request $request)
    {
        $search = $request->input('search', '');
        $strand = $request->input('strand', '');
        $status = $request->input('status', '');

        $query = Instructor::query()
            ->with(['user', 'strand'])
            ->whereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(role) = ?', ['instructor']));

        // Apply search filter
        if ($search) {
            $term = '%'.strtolower($search).'%';

            $query->where(function ($searchQuery) use ($term) {
                $searchQuery
                    ->whereHas('user', function ($userQuery) use ($term) {
                        $userQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                    })
                    ->orWhereRaw('LOWER(instructor_number) LIKE ?', [$term]);
            });
        }

        // Apply strand filter
        if ($strand) {
            $strandRecord = Strand::where('strand_code', $strand)->first();
            if ($strandRecord) {
                $query->where('strand_id', $strandRecord->strand_id);
            }
        }

        // Apply status filter
        if ($status) {
            $query->where('status', $status);
        }

        $instructors = $query->get()->map(function ($instructor) {
            $user = $instructor->user;

            return [
                'instructor_id' => $instructor->instructor_id,
                'user_id' => $instructor->user_id,
                'instructor_number' => $instructor->instructor_number,
                'first_name' => $user->name,
                'middle_name' => $user->middle_name ?? '',
                'last_name' => $user->last_name ?? '',
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'gender' => $user->gender ?? '',
                'strand_id' => $instructor->strand_id,
                'strand_code' => $instructor->strand?->strand_code ?? '',
                'rfid_tag' => $user->rfid_tag ?? '',
                'status' => $instructor->status ?? 'active',
            ];
        });

        // Get available strands for the form
        $strands = Strand::where('status', 'active')->get()->map(function ($strand) {
            return [
                'strand_id' => $strand->strand_id,
                'strand_code' => $strand->strand_code,
                'strand_name' => $strand->strand_name,
            ];
        });

        return Inertia::render('Admin/Instructors/InstructorsPage', [
            'title' => 'Instructor Management',
            'instructors' => $instructors,
            'strands' => $strands,
            'filters' => [
                'search' => $search,
                'strand' => $strand,
                'status' => $status,
            ],
        ]);
    }

    // @function create: Inihahanda ang create form o page.
    // @useIn create: InstructorsController::store (app/Http/Controllers/Admin/Instructors/InstructorsController.php)
    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    // @function store: Pinoproseso ang bagong Instructors record.
    // @useIn store: routes/web.php:422 (instructors.store)
    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'instructor_number' => ['required', Rule::unique('instructors', 'instructor_number')->whereNull('deleted_at')],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'rfid_tag' => ['nullable', 'string', Rule::unique('users', 'rfid_tag')->whereNull('deleted_at')],
            'status' => 'required|in:active,inactive,on_leave',
        ]);

        $temporaryPassword = $this->defaultPassword($validated['first_name'], $validated['last_name']);

        // Create user record
        $user = User::create([
            'name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'rfid_tag' => $validated['rfid_tag'] ?? null,
            'password' => Hash::make($temporaryPassword),
            'must_change_password' => true,
            'role' => 'instructor',
            'is_root_admin' => false,
        ]);

        // Create instructor record
        Instructor::create([
            'user_id' => $user->user_id,
            'strand_id' => $validated['strand_id'],
            'instructor_number' => $validated['instructor_number'],
            'status' => $validated['status'],
        ]);

        return back()->with(
            'success',
            'Instructor added successfully. Temporary password: '.$temporaryPassword.'.'
        );
    }

    // @function show: Ibinabalik ang detalye ng napiling record.
    // @useIn show: TODO(verify): walang direct caller na nakita sa static search
    /**
     * Display the specified resource.
     */
    public function show(Instructor $instructor)
    {
        //
    }

    // @function edit: Inihahanda ang edit form o page.
    // @useIn edit: TODO(verify): walang direct caller na nakita sa static search
    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Instructor $instructor)
    {
        //
    }

    // @function update: Pinoproseso ang pagbabago sa Instructors record.
    // @useIn update: routes/web.php:424 (instructors.update)
    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $instructor = Instructor::with('user')->findOrFail($id);

        $validated = $request->validate([
            'instructor_number' => ['required', Rule::unique('instructors', 'instructor_number')->whereNull('deleted_at')->ignore($id, 'instructor_id')],
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users', 'email')->whereNull('deleted_at')->ignore($instructor->user_id, 'user_id')],
            'phone' => 'nullable|string|max:20',
            'gender' => 'nullable|in:male,female',
            'strand_id' => 'required|exists:strands,strand_id',
            'rfid_tag' => ['nullable', 'string', Rule::unique('users', 'rfid_tag')->whereNull('deleted_at')->ignore($instructor->user_id, 'user_id')],
            'status' => 'required|in:active,inactive,on_leave',
        ]);

        // Update user record
        $instructor->user->update([
            'name' => $validated['first_name'],
            'middle_name' => $validated['middle_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'rfid_tag' => $validated['rfid_tag'] ?? null,
            'is_root_admin' => false,
        ]);

        // Update instructor record
        $instructor->update([
            'strand_id' => $validated['strand_id'],
            'instructor_number' => $validated['instructor_number'],
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Instructor updated successfully.');
    }

    // @function resetPassword: Nire-reset ang password sa Instructors flow.
    // @useIn resetPassword: routes/web.php:426 (instructors.password.reset-default)
    public function resetPassword(int $id)
    {
        $instructor = Instructor::query()->with('user')->findOrFail($id);
        $user = $instructor->user;

        abort_if(! $user || strtolower((string) $user->role) !== 'instructor', 404);

        $temporaryPassword = $this->defaultPassword(
            (string) $user->name,
            (string) $user->last_name
        );

        DB::transaction(function () use ($user, $temporaryPassword) {
            $user->forceFill([
                'password' => Hash::make($temporaryPassword),
                'must_change_password' => true,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $user->user_id)
                ->delete();
        });

        return back()->with(
            'success',
            'Instructor password reset to '.$temporaryPassword.'. They must create a private password at the next login.'
        );
    }

    // @function resetSecurityQuestions: Nire-reset ang security questions sa Instructors flow.
    // @useIn resetSecurityQuestions: routes/web.php:428 (instructors.security-questions.reset)
    public function resetSecurityQuestions(int $id)
    {
        $instructor = Instructor::query()->with('user')->findOrFail($id);
        $user = $instructor->user;

        abort_if(! $user || strtolower((string) $user->role) !== 'instructor', 404);

        DB::transaction(function () use ($user) {
            $user->forceFill([
                'security_question' => null,
                'security_answer_hash' => null,
                'security_questions' => null,
                'remember_token' => Str::random(60),
            ])->save();

            DB::table('sessions')
                ->where('user_id', $user->user_id)
                ->delete();
        });

        return back()->with(
            'success',
            'Instructor security questions reset. They must create three new security questions at the next verification.'
        );
    }

    // @function destroy: Pinoproseso ang pagtanggal ng Instructors record.
    // @useIn destroy: routes/web.php:431 (instructors.destroy)
    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $instructor = Instructor::with('user')->findOrFail($id);

        DB::transaction(function () use ($instructor) {
            $instructor->delete();
            $instructor->user?->delete();
        });

        return back()->with('success', 'Instructor deleted successfully.');
    }

    // @function defaultPassword: Binubuo ang default password string para sa Instructors.
    // @useIn defaultPassword: InstructorsController::store (app/Http/Controllers/Admin/Instructors/InstructorsController.php)
    private function defaultPassword(string $firstName, string $lastName): string
    {
        return Str::lower(preg_replace('/\s+/u', '', $firstName.$lastName) ?? '');
    }
}
