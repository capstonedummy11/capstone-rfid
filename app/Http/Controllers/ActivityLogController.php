<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController
{
    public function indexAdmin(Request $request)
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'table' => trim((string) $request->input('table', '')),
            'action' => trim((string) $request->input('action', '')),
        ];

        $query = ActivityLog::query()->with('user');

        if ($filters['search'] !== '') {
            $term = strtolower($filters['search']);
            $query->where(function ($logQuery) use ($term) {
                $logQuery
                    ->whereRaw('LOWER(description) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(table_name) LIKE ?', ["%{$term}%"])
                    ->orWhereRaw('LOWER(action) LIKE ?', ["%{$term}%"]);
            });
        }

        if ($filters['table'] !== '') {
            $query->where('table_name', $filters['table']);
        }

        if ($filters['action'] !== '') {
            $query->where('action', $filters['action']);
        }

        return Inertia::render('Auth/Admin/ActivityLogs', [
            'logs' => $query->orderByDesc('created_at')->get()->map(fn (ActivityLog $log) => [
                'logs_id' => $log->logs_id,
                'user_name' => $log->user?->name,
                'action' => $log->action,
                'table_name' => $log->table_name,
                'description' => $log->description,
                'created_at' => $log->created_at?->format('Y-m-d H:i:s'),
            ])->values(),
            'filters' => $filters,
            'tableOptions' => ActivityLog::query()->distinct()->orderBy('table_name')->pluck('table_name')->values(),
            'actionOptions' => ActivityLog::query()->distinct()->orderBy('action')->pluck('action')->values(),
        ]);
    }

    public function destroy(int $id)
    {
        ActivityLog::findOrFail($id)->delete();

        return back()->with('success', 'Activity log deleted successfully.');
    }
}