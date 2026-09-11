# Academic Year Final Verification

This document is the retained release checklist for the multi-academic-year implementation.

## Automated reconciliation

Run:

```bash
php artisan academic-years:check-integrity
php artisan academic-years:check-integrity --json
```

The command fails when it finds multiple active years, a non-deleted student without enrollment, incompatible attendance/enrollment context, online attendance outside the class roster, or orphaned normalized academic foreign keys.

## Covered lifecycle

Automated tests cover:

- creating, closing, reopening, archiving, and activating academic years;
- one active year at a time;
- same section name and subject catalog across different years;
- enrollment history without overwriting earlier placement;
- year-aware schedules, physical attendance, online attendance, reports, and exports;
- read-only closed-year records;
- transactional and idempotent rollover;
- source operational-history preservation;
- destination activation and active-schedule resolution;
- semester-only rollover updates, including current-semester subject-offering and schedule restrictions; and
- rejection of unauthorized lifecycle mutations.

## Required release commands

```bash
composer test:requested-features
php artisan test --compact
npm run build
git diff --check
```

## Manual/environment checks

These cannot be proven by the in-memory automated environment and must be checked before deployment or demonstration:

- RFID reader input on the real attendance panel;
- camera permissions and real face-provider success/fallback behavior;
- SMTP/SMS delivery with deployment credentials;
- PDF and Excel visual layout in target desktop applications;
- stored attachment existence in the deployment storage volume;
- browser navigation for every role and closed-year control state;
- production database backup and restore; and
- the fallback monitor remaining free of unexpected events during the observation window.

## Deployment sequence

1. Back up the database and storage volume.
2. Deploy the application and run migrations.
3. Run `academic-years:check-integrity` before activating a new year.
4. Review the Legacy Fallback Monitor on the Academic Years page.
5. Preview and review rollover mappings.
6. Execute rollover, close the source year, and activate the destination.
7. Confirm the Academic Years page shows the expected active semester.
8. Confirm Subjects and Schedules show only the current year and active semester for new configuration.
9. Run the integrity command again.
10. Perform the manual checks above.

Legacy columns must remain until the removal gate in `LEGACY_ACADEMIC_DEPENDENCY_AUDIT.md` is satisfied.
