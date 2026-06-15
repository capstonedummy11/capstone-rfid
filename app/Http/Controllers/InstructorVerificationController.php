<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AwsFaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InstructorVerificationController
{
    public function show(Request $request)
    {
        $user = $request->user();
        $securityQuestions = $this->storedSecurityQuestions($user);
        $selectedSecurityQuestion = $securityQuestions[0]['question'] ?? $user->security_question;
        $availableSecurityQuestions = SystemSetting::securityQuestions();

        if (strtolower((string) $user?->role) !== 'instructor') {
            return redirect()->route('dashboard');
        }

        if ((bool) $request->session()->get('instructor_verified', false)) {
            return redirect()->route('admin.dashboard');
        }

        return Inertia::render('Auth/InstructorVerify', [
            'title' => 'Instructor Verification',
            'hasFace' => count(array_filter($user->face_images ?? [])) > 0,
            'hasSecurityQuestion' => count($securityQuestions) >= 3 || (filled($user->security_question) && filled($user->security_answer_hash)),
            'securityQuestion' => $selectedSecurityQuestion,
            'securityQuestions' => collect($securityQuestions)
                ->map(fn (array $question) => ['question' => $question['question']])
                ->values(),
            'availableSecurityQuestions' => $availableSecurityQuestions,
            'email' => $user->email,
            'status' => session('success'),
        ]);
    }

    public function verifyFace(Request $request)
    {
        $validated = $request->validate([
            'image' => ['required', 'string'],
        ]);

        $user = $request->user();
        $faceImages = array_values(array_filter($user->face_images ?? []));

        if ($faceImages === []) {
            return back()->withErrors(['face' => 'No enrolled face image is available for this instructor.']);
        }

        $result = (new AwsFaceRecognitionService())->compareBase64WithStoredImage($validated['image'], $faceImages[0]);

        if ($result === null) {
            return back()->withErrors(['face' => 'Face recognition is not available. Use OTP or security question.']);
        }

        if (! $result['verified']) {
            return back()->withErrors(['face' => 'Face did not match the enrolled instructor photo.']);
        }

        $request->session()->put('instructor_verified', true);

        return redirect()->route('admin.dashboard');
    }

    public function sendOtp(Request $request)
    {
        $user = $request->user();
        $otp = (string) random_int(100000, 999999);

        $request->session()->put('instructor_login_otp', Hash::make($otp));
        $request->session()->put('instructor_login_otp_expires_at', now()->addMinutes(10)->timestamp);

        try {
            Mail::raw("Your instructor login OTP is {$otp}. It expires in 10 minutes.", function ($message) use ($user) {
                $message->to($user->email)->subject('Instructor Login OTP');
            });
        } catch (\Throwable $e) {
            Log::warning('Instructor OTP email could not be sent.', [
                'user_id' => $user->user_id,
                'message' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'OTP sent to your email if mail is configured.');
    }

    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $hash = $request->session()->get('instructor_login_otp');
        $expiresAt = (int) $request->session()->get('instructor_login_otp_expires_at', 0);

        if (! $hash || now()->timestamp > $expiresAt || ! Hash::check($validated['otp'], $hash)) {
            return back()->withErrors(['otp' => 'Invalid or expired OTP.']);
        }

        $request->session()->forget(['instructor_login_otp', 'instructor_login_otp_expires_at']);
        $request->session()->put('instructor_verified', true);

        return redirect()->route('admin.dashboard');
    }

    public function setupSecurity(Request $request)
    {
        $availableSecurityQuestions = SystemSetting::securityQuestions();

        $validated = $request->validate([
            'questions' => ['required', 'array', 'size:3'],
            'questions.*.question' => ['required', 'string', 'distinct', Rule::in($availableSecurityQuestions)],
            'questions.*.answer' => ['required', 'string', 'min:2', 'max:255'],
        ]);

        $questions = collect($validated['questions'])
            ->map(fn (array $question) => [
                'question' => $question['question'],
                'answer_hash' => Hash::make($this->normalizeAnswer($question['answer'])),
            ])
            ->values()
            ->all();

        $request->user()->update([
            'security_question' => $questions[0]['question'],
            'security_answer_hash' => $questions[0]['answer_hash'],
            'security_questions' => $questions,
        ]);

        return back()->with('success', 'Security questions saved.');
    }

    public function verifySecurity(Request $request)
    {
        $validated = $request->validate([
            'security_question' => ['required', 'string', 'max:255'],
            'security_answer' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $questions = $this->storedSecurityQuestions($user);
        $matchedQuestion = collect($questions)->firstWhere('question', $validated['security_question']);

        $answerHash = $matchedQuestion['answer_hash'] ?? (
            $validated['security_question'] === $user->security_question ? $user->security_answer_hash : null
        );

        if (! $answerHash || ! Hash::check($this->normalizeAnswer($validated['security_answer']), $answerHash)) {
            return back()->withErrors(['security_answer' => 'Security answer is incorrect.']);
        }

        $request->session()->put('instructor_verified', true);

        return redirect()->route('admin.dashboard');
    }

    private function normalizeAnswer(string $answer): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $answer)));
    }

    private function storedSecurityQuestions($user): array
    {
        $questions = collect($user->security_questions ?? [])
            ->filter(fn ($question) => filled($question['question'] ?? null) && filled($question['answer_hash'] ?? null))
            ->map(fn ($question) => [
                'question' => (string) $question['question'],
                'answer_hash' => (string) $question['answer_hash'],
            ])
            ->values()
            ->all();

        if ($questions === [] && filled($user->security_question) && filled($user->security_answer_hash)) {
            return [[
                'question' => $user->security_question,
                'answer_hash' => $user->security_answer_hash,
            ]];
        }

        return $questions;
    }
}
