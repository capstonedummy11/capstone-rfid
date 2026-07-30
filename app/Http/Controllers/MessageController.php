<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Instructor;
use App\Models\Message;
use App\Models\StudentPortalMessage;
use App\Models\Students;
use App\Models\User;
use App\Services\MessengerEmailNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class MessageController
{
    public function __construct(
        private readonly MessengerEmailNotificationService $emailNotifications,
    ) {}

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
        $student = $this->currentStudentContext($request);

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
        $portalThreadMessages = StudentPortalMessage::query()
            ->with(['sender:user_id,name,email', 'recipient:user_id,name,email'])
            ->where('instructor_user_id', $user->user_id)
            ->whereIn('student_id', $studentIds->values())
            ->oldest('created_at')
            ->get();

        $conversations = $inboxMessages
            ->groupBy(fn (Message $message) => strtolower((string) $message->sender_email).'|'.(string) $message->student_number)
            ->map(function ($messages, string $key) use ($participantIds, $portalThreadMessages, $studentIds, $user) {
                /** @var Message $latestInbox */
                $latestInbox = $messages->first();
                $participantId = $participantIds->get($latestInbox->sender_email);
                $studentId = $studentIds->get($latestInbox->student_number);

                $thread = $portalThreadMessages
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

        $messages = StudentPortalMessage::query()
            ->with(['sender:user_id,name,email,role', 'recipient:user_id,name,email,role', 'student'])
            ->where(function ($query) use ($user) {
                $query
                    ->where('sender_user_id', $user->user_id)
                    ->orWhere('recipient_user_id', $user->user_id);
            })
            ->latest('created_at')
            ->get()
            ->map(fn (StudentPortalMessage $message) => [
                'id' => $message->student_portal_message_id,
                'student_id' => $message->student_id,
                'student_name' => $message->student ? trim($message->student->first_name.' '.$message->student->last_name) : null,
                'sender_user_id' => $message->sender_user_id,
                'recipient_user_id' => $message->recipient_user_id,
                'sender' => $message->sender?->name,
                'sender_email' => $message->sender?->email,
                'sender_role' => $message->sender_role,
                'recipient' => $message->recipient?->name,
                'recipient_email' => $message->recipient?->email,
                'recipient_role' => $message->recipient?->role,
                'subject' => $message->subject ?: 'Conversation',
                'body' => $message->body,
                'preview' => str($message->body ?: $message->attachment_name ?: 'Attachment')->squish()->limit(82)->toString(),
                'attachment_name' => $message->attachment_name,
                'attachment_url' => $message->attachment_path ? route('messages.attachments.show', $message) : null,
                'attachment_preview_url' => $message->attachment_path && $this->isImageAttachment($message)
                    ? route('messages.attachments.show', ['message' => $message, 'preview' => 1])
                    : null,
                'attachment_mime' => $message->attachment_mime,
                'attachment_size' => $message->attachment_size,
                'is_image' => $this->isImageAttachment($message),
                'read_at' => $message->read_at?->toDateTimeString(),
                'created_at' => $message->created_at?->toDateTimeString(),
                'created_label' => $message->created_at?->diffForHumans(),
            ])
            ->values();

        return Inertia::render('Messages/Index', [
            'title' => 'Messages',
            'messages' => $messages,
            'conversations' => $conversations,
            'recipients' => $this->recipientOptions($user),
            'currentUserRole' => $role,
            'linkedStudents' => $role === 'parent' ? $this->linkedStudentsPayload($request) : [],
            'selectedStudentId' => $student?->student_id,
        ]);
    }

    public function sendConversationMessage(Request $request)
    {
        $user = $request->user();
        $role = strtolower(trim((string) $user?->role));
        $student = $this->currentStudentContext($request);

        $validated = $request->validate([
            'recipient_user_id' => ['required', 'integer', 'exists:users,user_id', Rule::notIn([$user->user_id])],
            'body' => ['nullable', 'required_without:attachment', 'string', 'max:5000'],
            'subject' => ['nullable', 'string', 'max:255'],
            'student_id' => ['nullable', 'integer', 'exists:students,student_id'],
            'attachment' => ['nullable', 'file', 'max:10240', 'mimes:pdf,doc,docx,jpg,jpeg,png,webp,gif,txt'],
        ]);

        $recipient = User::query()->findOrFail($validated['recipient_user_id']);
        abort_unless(in_array(strtolower((string) $recipient->role), $this->messageRoles(), true), 422, 'Selected recipient is not available.');

        $attachment = $request->file('attachment');
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;
        $attachmentSize = null;

        if ($attachment) {
            $attachmentPath = $attachment->store('student-portal-messages', 'public');
            $attachmentName = $attachment->getClientOriginalName();
            $attachmentMime = $attachment->getClientMimeType();
            $attachmentSize = $attachment->getSize();
        }

        $message = StudentPortalMessage::query()->create([
            'student_id' => $student?->student_id,
            'sender_user_id' => $user->user_id,
            'recipient_user_id' => $recipient->user_id,
            'sender_role' => $role,
            'instructor_user_id' => strtolower((string) $recipient->role) === 'instructor' ? $recipient->user_id : null,
            'subject' => filled($validated['subject'] ?? null) ? $validated['subject'] : 'Conversation',
            'body' => $validated['body'] ?? '',
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'attachment_size' => $attachmentSize,
        ]);

        $this->logActivity('create', 'student_portal_messages', 'Sent messenger message '.$message->student_portal_message_id.' from '.$user->email.' to '.$recipient->email);
        $this->emailNotifications->notify($user, $recipient, $message);

        return back()->with('success', 'Message sent.');
    }

    public function unreadStatus(Request $request)
    {
        $latest = StudentPortalMessage::query()
            ->with('sender:user_id,name')
            ->where('recipient_user_id', $request->user()->user_id)
            ->whereNull('read_at')
            ->latest('created_at')
            ->first();

        return response()->json([
            'unread_count' => StudentPortalMessage::query()
                ->where('recipient_user_id', $request->user()->user_id)
                ->whereNull('read_at')
                ->count(),
            'latest' => $latest ? [
                'id' => $latest->student_portal_message_id,
                'sender' => $latest->sender?->name ?: 'Someone',
                'preview' => str($latest->body ?: $latest->attachment_name ?: 'Attachment')
                    ->squish()
                    ->limit(80)
                    ->toString(),
                'created_at' => $latest->created_at?->toDateTimeString(),
            ] : null,
        ]);
    }

    public function markRead(Request $request, StudentPortalMessage $message)
    {
        $user = $request->user();

        abort_unless((int) $message->recipient_user_id === (int) $user->user_id, 403);

        if (! $message->read_at) {
            $message->update(['read_at' => now()]);
            $this->logActivity('update', 'student_portal_messages', 'Marked messenger message '.$message->student_portal_message_id.' as read');
        }

        return back();
    }

    public function downloadAttachment(Request $request, StudentPortalMessage $message)
    {
        $userId = (int) $request->user()?->user_id;

        abort_unless(
            $userId === (int) $message->sender_user_id || $userId === (int) $message->recipient_user_id,
            403,
        );
        abort_unless($message->attachment_path && Storage::disk('public')->exists($message->attachment_path), 404);

        if ($request->boolean('preview') && $this->isImageAttachment($message)) {
            return response()->file(Storage::disk('public')->path($message->attachment_path), [
                'Content-Type' => $message->attachment_mime ?: 'image/*',
                'Content-Disposition' => 'inline; filename="'.addslashes($message->attachment_name ?: 'message-image').'"',
            ]);
        }

        return Storage::disk('public')->download($message->attachment_path, $message->attachment_name ?: 'message-attachment');
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
        $recipient = $recipientUserId ? User::query()->find($recipientUserId) : null;
        if ($recipient) {
            $this->emailNotifications->notify($user, $recipient, $reply);
        }

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
            'attachment_preview_url' => $message->attachment_path && $this->isInboxImageAttachment($message)
                ? Storage::disk('public')->url($message->attachment_path)
                : null,
            'attachment_mime' => $message->attachment_mime,
            'attachment_size' => $message->attachment_size,
            'is_image' => $this->isInboxImageAttachment($message),
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
            'attachment_preview_url' => $message->attachment_path && in_array($attachmentExtension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
                ? Storage::disk('public')->url($message->attachment_path)
                : null,
            'attachment_mime' => $message->attachment_mime,
            'attachment_size' => $message->attachment_size,
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

    private function recipientOptions(?User $currentUser)
    {
        return User::query()
            ->where('user_id', '!=', $currentUser?->user_id)
            ->where(function ($query) {
                foreach ($this->messageRoles() as $role) {
                    $query->orWhereRaw('LOWER(role) = ?', [$role]);
                }
            })
            ->orderBy('name')
            ->get(['user_id', 'name', 'email', 'role'])
            ->map(fn (User $user) => [
                'user_id' => $user->user_id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => strtolower((string) $user->role),
            ])
            ->values();
    }

    private function messageRoles(): array
    {
        return ['admin', 'instructor', 'clinic', 'registrar', 'student', 'parent'];
    }

    private function isImageAttachment(StudentPortalMessage $message): bool
    {
        if ($message->attachment_mime && str_starts_with($message->attachment_mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $message->attachment_name, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function isInboxImageAttachment(Message $message): bool
    {
        if ($message->attachment_mime && str_starts_with($message->attachment_mime, 'image/')) {
            return true;
        }

        $extension = strtolower(pathinfo((string) $message->attachment_name, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
    }

    private function currentStudentContext(Request $request): ?Students
    {
        $role = strtolower((string) $request->user()?->role);

        if ($role === 'parent') {
            $query = $request->user()?->linkedStudents()->orderBy('students.student_id');

            if ($request->filled('student_id')) {
                $selected = (clone $query)->where('students.student_id', (int) $request->input('student_id'))->first();
                if ($selected) {
                    return $selected;
                }
            }

            return $query?->first();
        }

        if ($role === 'student') {
            return Students::query()->where('email', $request->user()?->email)->first();
        }

        return null;
    }

    private function linkedStudentsPayload(Request $request)
    {
        return $request->user()
            ?->linkedStudents()
            ->with(['section', 'strand'])
            ->orderBy('students.student_id')
            ->get()
            ->map(fn (Students $student) => [
                'student_id' => $student->student_id,
                'student_number' => $student->student_number,
                'name' => trim($student->first_name.' '.$student->last_name),
                'section' => $student->section?->section_name,
                'strand' => $student->strand?->strand_code,
                'school_year' => $student->school_year,
            ])
            ->values() ?? [];
    }
}
