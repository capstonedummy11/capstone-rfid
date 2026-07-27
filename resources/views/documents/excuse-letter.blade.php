<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <title>Excuse Letter</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111827; line-height: 1.6; }
        .page { width: 720px; margin: 48px auto; }
        h1 { text-align: center; font-size: 22px; margin-bottom: 56px; }
        p { font-size: 14px; margin: 0 0 18px; }
        .signature { margin-top: 72px; }
    </style>
</head>
<body>
    <div class="page">
        <h1>Excuse Letter</h1>

        <p>Date: {{ $letter->created_at?->format('F j, Y') }}</p>
        <p>Dear Instructor,</p>

        <p>
            Good day. I am {{ $studentName }} from {{ $section }}.
            I would like to request consideration for my absence or late attendance for
            {{ $letter->subject }} from {{ $letter->from_date?->format('F j, Y') }}
            to {{ $letter->to_date?->format('F j, Y') }}.
        </p>

        <p>{!! nl2br(e($letter->reason)) !!}</p>

        <p>
            I respectfully ask for your consideration regarding this matter.
            I will make sure to catch up on any missed requirements.
        </p>

        <div class="signature">
            <p>Sincerely,</p>
            <p>{{ $submittedBy }}</p>
        </div>
    </div>
</body>
</html>
