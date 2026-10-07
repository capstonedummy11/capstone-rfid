<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\AcademicYearRollover;
use App\Models\ActivityLog;
use App\Services\AcademicYearRolloverService;
use App\Services\AcademicYearService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AcademicYearController
{
    // @function __construct: Tinatanggap ang dependencies ng Academic Year sa pagbuo ng object.
    // @useIn __construct: Laravel dependency injection kapag ginagamit ang AcademicYearController
    // Ibinibigay ang lifecycle at rollover services sa controller.
    public function __construct(private readonly AcademicYearService $service, private readonly AcademicYearRolloverService $rolloverService) {}

    // @function index: Ibinabalik ang Auth/Admin/AcademicYears page at data para sa request.
    // @useIn index: routes/web.php:295 (academic-years.index)
    /**
     * @feature   Academic Year Lifecycle and Rollover
     * @actor     Admin
     * @flow      Dito ina-activate, kino-close, at niro-rollover ang academic year.
     * @uses      resources/js/pages/Auth/Admin/AcademicYears.vue; routes/web.php: AcademicYearController::index, AcademicYearController::store, AcademicYearController::update, AcademicYearController::activate, AcademicYearController::close, AcademicYearController::archive, AcademicYearController::reopen, AcademicYearController::rolloverPreview, AcademicYearController::rolloverExecute
     * @related   Admin workspace
     * @disable   1) I-comment out ang routes/web.php: AcademicYearController::index, AcademicYearController::store, AcademicYearController::update, AcademicYearController::activate, AcademicYearController::close, AcademicYearController::archive, AcademicYearController::reopen, AcademicYearController::rolloverPreview, AcademicYearController::rolloverExecute.
     * @disable   2) Itago ang action sa resources/js/pages/Auth/Admin/AcademicYears.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/AcademicYearController.php: AcademicYearController::index matapos alisin ang routes. Side effect: mawawala ang academic year lifecycle and rollover.
     */
    public function index()
    {
        return Inertia::render('Auth/Admin/AcademicYears', [
            'title' => 'Academic Years',
            'academicYears' => AcademicYear::query()
                ->orderByDesc('starts_on')
                ->get()
                ->map(fn (AcademicYear $year) => [
                    'academic_year_id' => $year->academic_year_id,
                    'name' => $year->name,
                    'starts_on' => $year->starts_on?->toDateString(),
                    'ends_on' => $year->ends_on?->toDateString(),
                    'status' => $year->status,
                    'active_semester' => $year->active_semester,
                    'activated_at' => $year->activated_at?->toDateTimeString(),
                    'closed_at' => $year->closed_at?->toDateTimeString(),
                    'reopened_at' => $year->reopened_at?->toDateTimeString(),
                    'reopen_reason' => $year->reopen_reason,
                ])
                ->values(),
            'activeAcademicYearId' => $this->service->active()?->academic_year_id,
            'rollovers' => AcademicYearRollover::query()->with(['sourceYear', 'destinationYear'])->latest()->get()->map(fn ($rollover) => [
                'id' => $rollover->academic_year_rollover_id,
                'source' => $rollover->sourceYear?->name,
                'destination' => $rollover->destinationYear?->name,
                'mode' => $rollover->mode,
                'status' => $rollover->status,
                'counts' => $rollover->execution_counts,
                'completed_at' => $rollover->completed_at?->toDateTimeString(),
            ]),
            'legacyFallbacks' => DB::table('legacy_academic_fallback_events')->orderByDesc('last_used_at')->get(),
        ]);
    }

    // @function store: Pinoproseso ang bagong Academic Year record.
    // @useIn store: routes/web.php:297 (academic-years.store)
    // Nagsa-save ng draft year matapos i-check ang year label at date range.
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/', 'unique:academic_years,name'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'active_semester' => ['nullable', Rule::in(['1st Semester', '2nd Semester'])],
        ]);

        [$start, $end] = array_map('intval', explode('-', $validated['name']));
        if ($end !== $start + 1) {
            return back()->withErrors(['name' => 'Academic year must contain consecutive years, for example 2026-2027.']);
        }
        if ((int) date('Y', strtotime($validated['starts_on'])) !== $start || (int) date('Y', strtotime($validated['ends_on'])) !== $end) {
            return back()->withErrors(['starts_on' => "The dates must fall within {$validated['name']}."]);
        }

        $year = AcademicYear::create($validated + ['status' => AcademicYear::STATUS_DRAFT]);
        $this->log($request, 'create', $year, "Created academic year {$year->name} as draft.");

        return back()->with('success', 'Academic year created as draft.');
    }

    // @function update: Pinoproseso ang pagbabago sa Academic Year record.
    // @useIn update: routes/web.php:299 (academic-years.update)
    // Writable draft year lang ang puwedeng baguhin pagkatapos ng validation.
    public function update(Request $request, AcademicYear $academicYear)
    {
        abort_unless($academicYear->isWritable(), 422, 'Closed or archived academic years cannot be edited.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:20', 'regex:/^\d{4}-\d{4}$/', Rule::unique('academic_years', 'name')->ignore($academicYear->academic_year_id, 'academic_year_id')],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after:starts_on'],
            'active_semester' => ['nullable', Rule::in(['1st Semester', '2nd Semester'])],
        ]);

        [$start, $end] = array_map('intval', explode('-', $validated['name']));
        if ($end !== $start + 1) {
            return back()->withErrors(['name' => 'Academic year must contain consecutive years, for example 2026-2027.']);
        }
        if ((int) date('Y', strtotime($validated['starts_on'])) !== $start || (int) date('Y', strtotime($validated['ends_on'])) !== $end) {
            return back()->withErrors(['starts_on' => "The dates must fall within {$validated['name']}."]);
        }

        $academicYear->update($validated);
        $this->log($request, 'update', $academicYear, "Updated academic year {$academicYear->name}.");

        return back()->with('success', 'Academic year updated.');
    }

    // @function activate: Ina-activate ang academic year sa Academic Year flow.
    // @useIn activate: routes/web.php:301 (academic-years.activate)
    // Ginagawang active ang year sa service at nilolog ang action.
    public function activate(Request $request, AcademicYear $academicYear)
    {
        $year = $this->service->activate($academicYear, $request->user());
        $this->log($request, 'activate', $year, "Activated academic year {$year->name}.");

        return back()->with('success', "{$year->name} is now the active academic year.");
    }

    // @function close: Isinasara ang academic year sa Academic Year flow.
    // @useIn close: routes/web.php:303 (academic-years.close)
    // Kino-close ang year sa service at nilolog ang action.
    public function close(Request $request, AcademicYear $academicYear)
    {
        $year = $this->service->close($academicYear, $request->user());
        $this->log($request, 'close', $year, "Closed academic year {$year->name}.");

        return back()->with('success', 'Academic year closed.');
    }

    // @function archive: Ina-archive ang academic year sa Academic Year flow.
    // @useIn archive: routes/web.php:305 (academic-years.archive)
    // Ina-archive ang year at nilolog ang pagbabago.
    public function archive(Request $request, AcademicYear $academicYear)
    {
        $year = $this->service->archive($academicYear);
        $this->log($request, 'archive', $year, "Archived academic year {$year->name}.");

        return back()->with('success', 'Academic year archived.');
    }

    // @function reopen: Binubuksan muli ang academic year sa Academic Year flow.
    // @useIn reopen: routes/web.php:307 (academic-years.reopen)
    // Nangangailangan ng dahilan bago ibalik ang closed year sa draft state.
    public function reopen(Request $request, AcademicYear $academicYear)
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $year = $this->service->reopen($academicYear, $request->user(), $validated['reason']);
        $this->log($request, 'reopen', $year, "Reopened academic year {$year->name}: {$validated['reason']}");

        return back()->with('success', 'Academic year reopened as a draft.');
    }

    // @function rolloverPreview: Pinoproseso ang rollover preview at nagbabalik ng JSON response.
    // @useIn rolloverPreview: routes/web.php:309 (academic-years.rollover-preview)
    // Nagbabalik ng preview para mareview ang destination bago magsulat ng data.
    public function rolloverPreview(Request $request, AcademicYear $academicYear)
    {
        $validated = $request->validate([
            'destination_academic_year_id' => ['required', 'integer', 'exists:academic_years,academic_year_id'],
            'mode' => ['nullable', Rule::in(['year', 'semester'])],
            'destination_semester' => ['nullable', Rule::in(['1st Semester', '2nd Semester'])],
        ]);

        return response()->json($this->rolloverService->preview(
            $academicYear,
            AcademicYear::findOrFail($validated['destination_academic_year_id']),
            $validated['mode'] ?? 'year',
            $validated['destination_semester'] ?? null,
        ));
    }

    // @function rolloverExecute: Kinukuha ang rollover execute result para sa Academic Year.
    // @useIn rolloverExecute: routes/web.php:311 (academic-years.rollover)
    // Ipinapasa ang validated mappings at decisions sa rollover service.
    public function rolloverExecute(Request $request, AcademicYear $academicYear)
    {
        $validated = $request->validate([
            'destination_academic_year_id' => ['required', 'integer', 'exists:academic_years,academic_year_id'],
            'mode' => ['nullable', Rule::in(['year', 'semester'])],
            'destination_semester' => ['nullable', Rule::in(['1st Semester', '2nd Semester'])],
            'section_mappings' => ['required', 'array'],
            'section_mappings.*.source_section_id' => ['required', 'integer', 'exists:sections,section_id'],
            'section_mappings.*.include' => ['required', 'boolean'],
            'section_mappings.*.destination_section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'section_mappings.*.destination_name' => ['nullable', 'string', 'max:255'],
            'section_mappings.*.destination_year_level' => ['nullable', 'integer', 'in:11,12'],
            'section_mappings.*.promotion_destination_source_section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'section_mappings.*.promotion_destination_section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'section_mappings.*.promotion_destination_name' => ['nullable', 'string', 'max:255'],
            'subject_selections' => ['nullable', 'array'],
            'subject_selections.*.source_subject_offering_id' => ['required', 'integer', 'exists:subject_offerings,subject_offering_id'],
            'subject_selections.*.include' => ['required', 'boolean'],
            'decisions' => ['nullable', 'array'],
            'decisions.*.source_student_enrollment_id' => ['required', 'integer', 'exists:student_enrollments,student_enrollment_id'],
            'decisions.*.decision' => ['required', 'in:promote,retain,graduated,dropped,transferred,review'],
            'decisions.*.destination_section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
            'decisions.*.destination_source_section_id' => ['nullable', 'integer', 'exists:sections,section_id'],
        ]);
        $rollover = $this->rolloverService->execute(
            $academicYear, AcademicYear::findOrFail($validated['destination_academic_year_id']), $request->user(),
            $validated['decisions'] ?? [], $validated['section_mappings'], $validated['mode'] ?? 'year', $validated['destination_semester'] ?? null,
            $validated['subject_selections'] ?? null,
        );

        return back()->with('success', "Rollover completed safely. {$rollover->items->where('status', 'completed')->count()} student decisions were applied.");
    }

    // @function log: Nilolog ang academic year sa Academic Year flow.
    // @useIn log: AcademicYearController::store (app/Http/Controllers/AcademicYearController.php)
    // Nagtatala ng actor, action, at year sa general activity log.
    private function log(Request $request, string $action, AcademicYear $year, string $description): void
    {
        ActivityLog::create([
            'user_id' => $request->user()?->user_id,
            'action' => $action,
            'table_name' => 'academic_years',
            'module' => 'academic_years',
            'subject_type' => AcademicYear::class,
            'subject_id' => (string) $year->academic_year_id,
            'description' => $description,
        ]);
    }
}
