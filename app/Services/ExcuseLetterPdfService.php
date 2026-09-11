<?php

namespace App\Services;

use App\Models\StudentExcuseLetter;
use App\Models\SystemSetting;

class ExcuseLetterPdfService
{
    public function render(StudentExcuseLetter $letter, string $studentName, string $section, string $submittedBy): string
    {
        $lines = [
            ['Excuse Letter', 18, true],
            ['', 12, false],
            ['Date: '.$letter->created_at?->format('F j, Y'), 11, false],
            ['Dear Instructor,', 11, false],
            ['', 11, false],
            ...$this->wrap(
                'Good day. I am '.$studentName.' from '.$section.'. I would like to request consideration for my absence or late attendance for '.$letter->subject.' from '.$letter->from_date?->format('F j, Y').' to '.$letter->to_date?->format('F j, Y').'.',
            ),
            ['', 11, false],
            ...$this->wrap((string) $letter->reason),
            ['', 11, false],
            ...$this->wrap('I respectfully ask for your consideration regarding this matter. I will make sure to catch up on any missed requirements.'),
            ['', 11, false],
            ['Sincerely,', 11, false],
            [$submittedBy, 11, false],
        ];

        if (SystemSetting::featureFlags()['parent_excuse_letters_enabled']) {
            array_push($lines,
                ['', 11, false],
                ['Parent Approval', 12, true],
                ['Status: '.$this->statusLabel((string) $letter->status), 11, false],
                ['Parent Signature: '.($letter->parent_signature ?: ''), 11, false],
                ['Approved By: '.($letter->parentApprovedBy?->name ?: 'Pending'), 11, false],
                ['Approved At: '.($letter->parent_approved_at?->format('F j, Y g:i A') ?: 'Pending'), 11, false],
            );
        }

        if ($letter->parent_approval_notes && SystemSetting::featureFlags()['parent_excuse_letters_enabled']) {
            $lines[] = ['', 11, false];
            $lines[] = ['Parent Notes', 12, true];
            array_push($lines, ...$this->wrap($letter->parent_approval_notes));
        }

        return $this->pdf($lines);
    }

    private function wrap(string $text, int $limit = 86): array
    {
        $rows = [];

        foreach (preg_split('/\R/', $text) ?: [] as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                $rows[] = ['', 11, false];
                continue;
            }

            $line = '';
            foreach (preg_split('/\s+/', $paragraph) ?: [] as $word) {
                $next = trim($line.' '.$word);
                if (strlen($next) > $limit && $line !== '') {
                    $rows[] = [$line, 11, false];
                    $line = $word;
                } else {
                    $line = $next;
                }
            }

            if ($line !== '') {
                $rows[] = [$line, 11, false];
            }
        }

        return $rows;
    }

    private function pdf(array $lines): string
    {
        $content = "BT\n";
        $y = 760;

        foreach ($lines as [$text, $size, $bold]) {
            if ($text === '') {
                $y -= 16;
                continue;
            }

            $font = $bold ? 'F2' : 'F1';
            $x = $bold && $size >= 18 ? 250 : 72;
            $content .= sprintf("/%s %d Tf\n1 0 0 1 %d %d Tm\n(%s) Tj\n", $font, $size, $x, $y, $this->escape($text));
            $y -= $size + 7;
        }

        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>',
            "<< /Length ".strlen($content)." >>\nstream\n{$content}endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    private function statusLabel(string $status): string
    {
        return ucwords(str_replace('_', ' ', $status));
    }
}
