<?php

namespace App\Http\Controllers;

use App\Models\SystemSetting;
use App\Services\AwsFaceRecognitionService;
use App\Services\AwsFaceLivenessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class InstructorVerificationController
{
    // @function show: Ibinabalik ang Auth/InstructorVerify page at data para sa request.
    // @useIn show: routes/web.php:213 (verify)
    /**
     * @feature   Login Verification
     * @actor     Instructor
     * @flow      Pagkatapos ng login, dito kinukumpleto ang face, email OTP, o security-question check.
     * @uses      resources/js/pages/Auth/InstructorVerify.vue; routes/web.php: InstructorVerificationController::show, InstructorVerificationController::verifyFace, InstructorVerificationController::sendOtp, InstructorVerificationController::verifyOtp, InstructorVerificationController::setupSecurity, InstructorVerificationController::verifySecurity
     * @related   Instructor workspace
     * @disable   1) I-comment out ang routes/web.php: InstructorVerificationController::show, InstructorVerificationController::verifyFace, InstructorVerificationController::sendOtp, InstructorVerificationController::verifyOtp, InstructorVerificationController::setupSecurity, InstructorVerificationController::verifySecurity.
     * @disable   2) Itago ang action sa resources/js/pages/Auth/InstructorVerify.vue; kung may menu link, alisin ito sa resources/js/layouts/AuthNavbar.vue.
     * @disable   3) Ihinto ang app/Http/Controllers/InstructorVerificationController.php: InstructorVerificationController::show matapos alisin ang routes. Side effect: mawawala ang login verification.
     */
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

    // @function verifyFace: Vini-verify ang face sa Instructor Verification flow.
    // @useIn verifyFace: routes/web.php:215 (verify.face)
    public function verifyFace(Request $request, AwsFaceLivenessService $livenessService)
    {
        $validated = $request->validate([
            'image' => ['nullable', 'string'],
            'liveness_token' => ['nullable', 'string', 'size:64'],
        ]);

        $user = $request->user();
        $faceImages = array_values(array_filter($user->face_images ?? []));

        if ($faceImages === []) {
            return back()->withErrors(['face' => 'No enrolled face image is available for this instructor.']);
        }

        $capturedImage = (string) ($validated['image'] ?? '');
        if (config('services.aws_rekognition.liveness.enabled', false)) {
            $capturedImage = $livenessService->consumeReferenceImage(
                $request,
                (string) ($validated['liveness_token'] ?? ''),
                'instructor_login',
                (string) $user->user_id,
            ) ?? '';
        }

        if ($capturedImage === '') {
            return back()->withErrors(['face' => 'A valid live-face verification is required.']);
        }

        $result = (new AwsFaceRecognitionService)->compareBase64WithStoredImage($capturedImage, $faceImages[0]);

        if ($result === null) {
            return back()->withErrors(['face' => 'Face recognition is not available. Use OTP or security question.']);
        }

        if (! $result['verified']) {
            return back()->withErrors(['face' => 'Face did not match the enrolled instructor photo.']);
        }

        $request->session()->put('instructor_verified', true);

        return redirect()->route('admin.dashboard');
    }

    // @function sendOtp: Ipinapadala ang otp sa Instructor Verification flow.
    // @useIn sendOtp: routes/web.php:217 (verify.otp.send)
    public function sendOtp(Request $request)
    {
        $user = $request->user();
        if (! $user || ! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return back()->withErrors(['otp' => 'A valid instructor email address is required before an OTP can be sent.']);
        }

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

            $request->session()->forget(['instructor_login_otp', 'instructor_login_otp_expires_at']);

            return back()->withErrors(['otp' => 'The verification code could not be emailed right now. Please try again shortly or use face or security-question verification.']);
        }

        return back()->with('success', 'OTP sent to '.$user->email.'. It expires in 10 minutes.');
    }

    // @function verifyOtp: Vini-verify ang otp sa Instructor Verification flow.
    // @useIn verifyOtp: routes/web.php:219 (verify.otp)
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

    // @function setupSecurity: Kinukuha ang setup security result para sa Instructor Verification.
    // @useIn setupSecurity: routes/web.php:221 (verify.security.setup)
    public function setupSecurity(Request $request)
    {
        $availableSecurityQuestions = SystemSetting::securityQuestions();

        $validated = $request->validate(
            [
                'questions' => ['required', 'array', 'size:3'],
                'questions.*.question' => ['required', 'string', 'distinct', Rule::in($availableSecurityQuestions)],
                'questions.*.answer' => ['required', 'string', 'min:2', 'max:255'],
            ],
            [],
            [
                'questions.0.question' => 'Question 1',
                'questions.0.answer' => 'Answer 1',
                'questions.1.question' => 'Question 2',
                'questions.1.answer' => 'Answer 2',
                'questions.2.question' => 'Question 3',
                'questions.2.answer' => 'Answer 3',
            ],
        );

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

    // @function verifySecurity: Vini-verify ang security sa Instructor Verification flow.
    // @useIn verifySecurity: routes/web.php:223 (verify.security)
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

    // @function normalizeAnswer: Nino-normalize ang answer sa Instructor Verification flow.
    // @useIn normalizeAnswer: InstructorVerificationController::setupSecurity (app/Http/Controllers/InstructorVerificationController.php)
    private function normalizeAnswer(string $answer): string
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', $answer)));
    }

    // @function storedSecurityQuestions: Kinukuha ang stored security questions result para sa Instructor Verification.
    // @useIn storedSecurityQuestions: InstructorVerificationController::show (app/Http/Controllers/InstructorVerificationController.php)
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
