<?php

namespace App\Http\Controllers;

use App\Models\Students;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;

class RfidController
{
    public function index(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'type' => trim((string) $request->input('type', 'all')),
        ];

        $studentQuery = Students::query()
            ->leftJoin('courses', 'students.course_id', '=', 'courses.course_id')
            ->leftJoin('sections', 'students.section_id', '=', 'sections.section_id');

        $userQuery = User::query();

        if ($filters['search'] !== '') {
            $term = '%' . $filters['search'] . '%';
            $studentQuery->where(function ($q) use ($term) {
                $q->where('students.first_name', 'like', $term)
                    ->orWhere('students.last_name', 'like', $term)
                    ->orWhere('students.student_number', 'like', $term)
                    ->orWhere('students.rfid_tag', 'like', $term);
            });

            $userQuery->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('rfid_tag', 'like', $term);
            });
        }

        if ($filters['type'] === 'students') {
            $userQuery->whereRaw('1 = 0');
        } elseif ($filters['type'] === 'instructors') {
            $studentQuery->whereRaw('1 = 0');
        }

        $students = $studentQuery->select([
            'students.student_id as id',
            'students.student_number as owner_id',
            'students.first_name',
            'students.middle_name',
            'students.last_name',
            'students.rfid_tag',
            'students.year_level',
            'courses.course_code as course',
            'sections.section_name as section',
        ])->get()->map(function ($item) {
            return [
                'id' => $item->id,
                'ownerId' => $item->owner_id,
                'name' => trim(($item->first_name ?? '') . ' ' . ($item->middle_name ?? '') . ' ' . ($item->last_name ?? '')),
                'role' => 'Student',
                'course' => $item->course ?? 'N/A',
                'section' => $item->section ?? 'N/A',
                'year' => $item->year_level ?? 'N/A',
                'rfid' => $item->rfid_tag ?? '',
                'type' => 'student',
            ];
        });

        $users = $userQuery
            ->where('role', 'instructor')
            ->select(['user_id as id', 'name', 'email', 'role', 'rfid_tag'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'ownerId' => 'INS-' . $item->id,
                    'name' => $item->name ?? 'Unknown Instructor',
                    'role' => ucfirst($item->role ?? 'Instructor'),
                    'course' => 'Faculty',
                    'section' => 'N/A',
                    'year' => 'N/A',
                    'rfid' => $item->rfid_tag ?? '',
                    'type' => 'instructor',
                ];
            });

        $rows = $students->concat($users)
            ->sortBy(function ($item) {
                return strtolower($item['name'] ?? '');
            })
            ->values();

        $unassignedStudents = Students::query()
            ->whereNull('rfid_tag')
            ->orWhere('rfid_tag', '')
            ->select(['student_id as id', 'student_number as owner_id', 'first_name', 'middle_name', 'last_name'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'ownerId' => $item->owner_id,
                    'name' => trim(($item->first_name ?? '') . ' ' . ($item->middle_name ?? '') . ' ' . ($item->last_name ?? '')),
                    'type' => 'student',
                ];
            });

        $unassignedInstructors = User::query()
            ->where('role', 'instructor')
            ->where(function ($q) {
                $q->whereNull('rfid_tag')->orWhere('rfid_tag', '');
            })
            ->select(['user_id as id', 'name'])
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'ownerId' => 'INS-' . $item->id,
                    'name' => $item->name ?? 'Unknown Instructor',
                    'type' => 'instructor',
                ];
            });

        return Inertia::render('Rfid', [
            'rfidRows' => $rows,
            'filters' => $filters,
            'unassignedOwners' => $unassignedStudents->concat($unassignedInstructors)->values(),
        ]);
    }

    public function update(Request $request, string $type, string $id)
    {
        $request->validate([
            'rfid_tag' => ['nullable', 'string', 'max:255'],
        ]);

        $rfidTag = trim((string) $request->input('rfid_tag', '')) ?: null;

        if ($type === 'student') {
            /** @var Students|null $model */
            $model = Students::where('student_id', $id)->first();
            if (!$model) {
                return Redirect::back()->with('error', 'Student not found.');
            }
            $model->update(['rfid_tag' => $rfidTag]);
        } elseif ($type === 'instructor') {
            /** @var User|null $model */
            $model = User::where('user_id', $id)->first();
            if (!$model) {
                return Redirect::back()->with('error', 'Instructor not found.');
            }
            $model->update(['rfid_tag' => $rfidTag]);
        } else {
            return Redirect::back()->with('error', 'Unknown RFID owner type.');
        }

        return Redirect::route('admin.rfid')->with('success', 'RFID updated successfully.');
    }

    public function destroy(string $type, string $id)
    {
        if ($type === 'student') {
            $model = Students::find($id);
        } elseif ($type === 'instructor') {
            $model = User::find($id);
        } else {
            return Redirect::back()->with('error', 'Unknown RFID owner type.');
        }

        if (!$model) {
            return Redirect::back()->with('error', 'Owner not found.');
        }

        /** @var \Illuminate\Database\Eloquent\Model $model */
        $model->update(['rfid_tag' => null]);

        return Redirect::route('admin.rfid')->with('success', 'RFID removed successfully.');
    }
}
