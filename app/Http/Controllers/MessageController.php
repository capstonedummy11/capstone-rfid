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

        $validated['subject'] = filled($validated['subject'] ?? null)
            ? $validated['subject']
            : 'Instructor conversation';

        $message = Message::query()->create($validated);

        $this->logActivity('create', 'messages', 'Created instructor inbox message '.$message->message_id.' from '.$validated['sender_type'].' '.$validated['sender_name']);

        return back()->with('success', 'Message sent to the instructor.');
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        $inboxMessages = Message::query()
            ->with('instructor:user_id,name,email')
            ->where('instructor_user_id', $user->user_id)
            ->latest('created_at')
            ->get();

        $studentIds = Students::query()
            ->whereIn('student_number', $inboxMessages->pluck('student_number')->filter()->unique())
            ->pluck('student_id', 'student_number');
        $participantIds = User::query()
            ->whereIn('email', $inboxMessages->pluck('sender_email')->filter()->unique())
            ->pluck('user_id', 'email');
        $portalMessages = StudentPortalMessage::query()
            ->with(['sender:user_id,name,email', 'recipient:user_id,name,email'])
            ->where('instructor_user_id', $user->user_id)
            ->whereIn('student_id', $studentIds->values())
            ->oldest('created_at')
            ->get();

        $conversations = $inboxMessages
            ->groupBy(fn (Message $message) => strtolower((string) $message->sender_email).'|'.(string) $message->student_number)
            ->map(function ($messages, string $key) use ($participantIds, $portalMessages, $studentIds, $user) {
                /** @var Message $latestInbox */
                $latestInbox = $messages->first();
                $participantId = $participantIds->get($latestInbox->sender_email);
                $studentId = $studentIds->get($latestInbox->student_number);

                $thread = $portalMessages
                    ->filter(function (StudentPortalMessage $portalMessage) use ($participantId, $studentId, $user) {
                        if (! $participantId || (int) $portalMessage->student_id !== (int) $studentId) {
                            return false;
                        }

                        return in_array((int) $participantId, [
                            (int) $portalMessage->sender_user_id,
                            (int) $portalMessage->recipient_user_id,
                        ], true) && in_array((int) $user->user_id, [
                            (int) $portalMessage->sender_user_id,
                            (int) $portalMessage->recipient_user_id,
                        ], true);
                    })
                    ->map(fn (StudentPortalMessage $portalMessage) => $this->portalThreadMessage($portalMessage, (int) $user->user_id));

                // Keep public/legacy inbox entries while avoiding the mirrored copy of portal messages.
                $messages->each(function (Message $inboxMessage) use (&$thread) {
                    $isMirrored = $thread->contains(function (array $threadMessage) use ($inboxMessage) {
                        if ($threadMessage['direction'] !== 'incoming' || $threadMessage['body'] !== $inboxMessage->body) {
                            return false;
                        }

                        return abs(strtotime((string) $threadMessage['created_at']) - $inboxMessage->created_at->timestamp) <= 5;
                    });

                    if (! $isMirrored) {
                        $thread->push($this->inboxThreadMessage($inboxMessage));
                    }
                });
                $thread = $thread->sort(function (array $left, array $right) {
                    $timeComparison = strcmp((string) $left['created_at'], (string) $right['created_at']);

                    if ($timeComparison !== 0) {
                        return $timeComparison;
                    }

                    return ($left['direction'] === 'incoming' ? 0 : 1) <=> ($right['direction'] === 'incoming' ? 0 : 1);
                })->values();

                $latestThreadMessage = $thread->last();

                return [
                    'key' => sha1($key),
                    'reply_message_id' => $latestInbox->message_id,
                    'participant' => [
                        'user_id' => $participantId,
                        'name' => $latestInbox->sender_name,
                        'email' => $latestInbox->sender_email,
                        'role' => $latestInbox->sender_type,
                        'student_number' => $latestInbox->student_number,
                    ],
                    'messages' => $thread->values(),
                    'preview' => str($latestThreadMessage['body'] ?? '')->squish()->limit(82)->toString(),
                    'latest_at' => $latestThreadMessage['created_at'] ?? null,
                    'latest_label' => $latestThreadMessage['created_label'] ?? null,
                    'unread' => $messages->contains(fn (Message $message) => ! $message->read_at),
                ];
            })
            ->sortByDesc('latest_at')
            ->values();

        return Inertia::render('Messages/Index', [
            'title' => 'Messages',
            'conversations' => $conversations,
            'currentUserRole' => $role,
        ]);
    }

    public function markRead(Request $request, Message $message)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        abort_unless((int) $message->instructor_user_id === (int) $user->user_id, 403);

        if (! $message->read_at) {
            Message::query()
                ->where('instructor_user_id', $user->user_id)
                ->where('student_number', $message->student_number)
                ->where('sender_email', $message->sender_email)
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            $this->logActivity('update', 'messages', 'Marked instructor inbox message '.$message->message_id.' as read');
        }

        return back();
    }

    public function reply(Request $request, Message $message)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));

        abort_unless((int) $message->instructor_user_id === (int) $user->user_id, 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $student = Students::query()
            ->where('student_number', $message->student_number)
            ->first();

        abort_unless($student, 422, 'The original message is not linked to a student record.');

        $recipientUserId = User::query()
            ->where('email', $message->sender_email)
            ->value('user_id');

        $reply = StudentPortalMessage::query()->create([
            'student_id' => $student->student_id,
            'sender_user_id' => $user->user_id,
            'recipient_user_id' => $recipientUserId,
            'sender_role' => 'instructor',
            'instructor_user_id' => $message->instructor_user_id,
            'subject' => 'Re: '.($message->subject ?: 'Instructor conversation'),
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

    private function inboxThreadMessage(Message $message): array
    {
        return [
            'id' => 'inbox-'.$message->message_id,
            'direction' => 'incoming',
            'sender_name' => $message->sender_name,
            'body' => $message->body,
            'attachment_name' => $message->attachment_name,
            'attachment_url' => $message->attachment_path ? Storage::disk('public')->url($message->attachment_path) : null,
            'is_image' => $message->attachment_mime ? str_starts_with($message->attachment_mime, 'image/') : false,
            'created_at' => $message->created_at?->toDateTimeString(),
            'created_label' => $message->created_at?->diffForHumans(),
        ];
    }

    private function portalThreadMessage(StudentPortalMessage $message, int $currentUserId): array
    {
        $attachmentExtension = strtolower(pathinfo((string) $message->attachment_name, PATHINFO_EXTENSION));

        return [
            'id' => 'portal-'.$message->student_portal_message_id,
            'direction' => (int) $message->sender_user_id === $currentUserId ? 'outgoing' : 'incoming',
            'sender_name' => $message->sender?->name,
            'body' => $message->body,
            'attachment_name' => $message->attachment_name,
            'attachment_url' => $message->attachment_path ? Storage::disk('public')->url($message->attachment_path) : null,
            'is_image' => in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true),
            'created_at' => $message->created_at?->toDateTimeString(),
            'created_label' => $message->created_at?->diffForHumans(),
        ];
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
