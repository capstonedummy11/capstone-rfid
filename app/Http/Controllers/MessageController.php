<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MessageController
{
    public function create()
    {
        return Inertia::render('Messages/Create', [
            'title' => 'Message Instructor',
            'instructors' => $this->instructorOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'instructor_user_id' => ['required', 'integer', 'exists:users,user_id'],
            'sender_type' => ['required', 'in:student,parent'],
            'sender_name' => ['required', 'string', 'max:255'],
            'sender_email' => ['nullable', 'email', 'max:255'],
            'student_number' => ['nullable', 'string', 'max:255'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:5000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $instructorExists = User::query()
            ->where('user_id', $validated['instructor_user_id'])
            ->whereRaw('LOWER(role) = ?', ['instructor'])
            ->exists();

        abort_unless($instructorExists, 422, 'Selected instructor is not available.');

        $attachment = $request->file('attachment');
        if ($attachment) {
            $validated['attachment_path'] = $attachment->store('message-attachments', 'public');
            $validated['attachment_name'] = $attachment->getClientOriginalName();
            $validated['attachment_mime'] = $attachment->getClientMimeType();
            $validated['attachment_size'] = $attachment->getSize();
        }

        unset($validated['attachment']);

        $message = Message::query()->create($validated);

        $this->logActivity('create', 'messages', 'Created instructor inbox message '.$message->message_id.' from '.$validated['sender_type'].' '.$validated['sender_name']);

        return back()->with('success', 'Message sent to the instructor.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        $messages = Message::query()
            ->with('instructor:user_id,name,email')
            ->when($role === 'instructor', fn ($query) => $query->where('instructor_user_id', $user->user_id))
            ->latest('created_at')
            ->get()
            ->map(fn (Message $message) => [
                'id' => $message->message_id,
                'sender_type' => $message->sender_type,
                'sender_name' => $message->sender_name,
                'sender_email' => $message->sender_email,
                'student_number' => $message->student_number,
                'subject' => $message->subject ?: 'No subject',
                'body' => $message->body,
                'preview' => str($message->body)->squish()->limit(82)->toString(),
                'instructor_name' => $message->instructor?->name,
                'attachment_name' => $message->attachment_name,
                'attachment_url' => $message->attachment_path ? Storage::disk('public')->url($message->attachment_path) : null,
                'is_image' => $message->attachment_mime ? str_starts_with($message->attachment_mime, 'image/') : false,
                'read_at' => $message->read_at?->toDateTimeString(),
                'created_at' => $message->created_at?->toDateTimeString(),
                'created_label' => $message->created_at?->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Messages/Index', [
            'title' => 'Messages',
            'messages' => $messages,
            'currentUserRole' => $role,
        ]);
    }

    public function markRead(Request $request, Message $message)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        abort_unless($role === 'admin' || (int) $message->instructor_user_id === (int) $user->user_id, 403);

        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
            $this->logActivity('update', 'messages', 'Marked instructor inbox message '.$message->message_id.' as read');
        }

        return back();
    }

    public function reply(Request $request, Message $message)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        abort_unless($role === 'admin' || (int) $message->instructor_user_id === (int) $user->user_id, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $student = Students::query()
            ->where('student_number', $message->student_number)
            ->first();

        abort_unless($student, 422, 'The original message is not linked to a student record.');

        $reply = StudentPortalMessage::query()->create([
            'student_id' => $student->student_id,
            'sender_user_id' => $user->user_id,
            'sender_role' => 'instructor',
            'instructor_user_id' => $message->instructor_user_id,
            'subject' => 'Re: '.$message->subject,
            'body' => $validated['body'],
        ]);

        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
        }

        $this->logActivity('create', 'student_portal_messages', 'Replied to student portal message thread via inbox message '.$message->message_id.' with portal message '.$reply->student_portal_message_id);

        return back()->with('success', 'Reply sent to the student portal.');
    }

    private function logActivity(string $action, string $tableName, string $description): void
    {
        ActivityLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'table_name' => $tableName,
            'description' => $description,
        ]);
    }

    private function instructorOptions()
    {
        return Instructor::query()
            ->with('user:user_id,name,email')
            ->whereHas('user', fn ($query) => $query->whereRaw('LOWER(role) = ?', ['instructor']))
            ->where(function ($query) {
                $query->whereNull('status')->orWhere('status', 'active');
            })
            ->get()
            ->filter(fn (Instructor $instructor) => $instructor->user)
            ->map(fn (Instructor $instructor) => [
                'value' => $instructor->user->user_id,
                'label' => $instructor->user->name,
                'email' => $instructor->user->email,
            ])
            ->sortBy('label')
            ->values();
    }
}
