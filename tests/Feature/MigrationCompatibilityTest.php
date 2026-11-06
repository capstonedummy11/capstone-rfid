<?php

test('instructor review migration uses short explicit foreign key names and supports a retry', function () {
    $migration = file_get_contents(database_path('migrations/2026_09_21_000001_add_instructor_review_to_excuse_letters.php'));

    preg_match_all("/->foreign\([^,]+,\s*'([^']+)'\)/", $migration, $matches);

    expect($matches[1])
        ->toContain('spm_excuse_letter_fk', 'spm_excuse_reviewer_fk');

    collect($matches[1])->each(
        fn (string $constraint) => expect(strlen($constraint))->toBeLessThanOrEqual(64),
    );

    expect($migration)
        ->toContain("Schema::hasColumn('student_portal_messages', \$column)")
        ->toContain("Schema::getForeignKeys('student_portal_messages')");
});
