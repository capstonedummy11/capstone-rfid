# Demonstration Documentation Map

Several files discuss demonstrations because they serve different presentation needs. This document is the canonical guide for choosing among them and keeps their shared context in one place.

Return to the [Documentation Index](DOCUMENTATION_INDEX.md).

## Shared Demonstration Context

The demonstration should prove one connected workflow:

1. Admin configures settings, rooms, academic structure, users, instructors, students, parents, schedules, inventory, and security controls.
2. Registrar enrolls student RFID and biometric data.
3. Instructor completes login verification and works only with assigned classes.
4. Console starts a room- and schedule-bound attendance session.
5. Student taps demonstrate check-in, Late classification, temporary movement, checkout, and invalid/ignored safeguards.
6. Instructor reviews or corrects assigned attendance within the configured edit window.
7. Student and parent use attendance, excuse letters, notifications, and Messenger.
8. Staff demonstrate online classes, clinic/emergency workflows, inventory, reports, and audit logs.

Exact RFID decisions always come from [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md). Complete application behavior comes from [System Flow](SYSTEM_FLOW.md).

## Which Demonstration File to Use

| File | Purpose | Best time to use it |
|---|---|---|
| [CAPSTONE_PRESENTATION.md](CAPSTONE_PRESENTATION.md) | Slide titles, claims, and speaker structure | Building or updating slides |
| [FULL_SYSTEM_DEMONSTRATION_SCRIPT.md](FULL_SYSTEM_DEMONSTRATION_SCRIPT.md) | Short sequence of actions and talking points | During the live demo |
| [SYSTEM_FLOW_DEMONSTRATION.md](SYSTEM_FLOW_DEMONSTRATION.md) | Detailed presenter action, explanation, expected result, rules, and recovery | Rehearsal and evaluator preparation |
| [ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md](ATTENDANCE_SYSTEM_DEMONSTRATION_GUIDE.md) | Blank-state setup and deep attendance scenarios | Attendance-focused technical rehearsal |

These documents are complementary formats, not independent sources of business rules.

## Standard Presentation Rule

Every demonstrated claim should be traceable to a canonical source:

- System capabilities and limitations: [Project README](README.md)
- End-to-end feature behavior: [System Flow](SYSTEM_FLOW.md)
- Attendance tapping: [Attendance Control Panel Tapping Rules](ATTENDANCE_CONTROL_PANEL_TAPPING_RULES.md)
- Operator steps: [User Operations Tutorial](USER_OPERATIONS_TUTORIAL.md)
- Database claims: [Database Documentation](DATABASE.md)
- Verified implementation and gaps: [Repository Analysis and Flow Report](REPOSITORY_ANALYSIS_AND_FLOW_REPORT.md)

## Avoiding Duplicate Updates

When behavior changes:

1. Change the canonical source first.
2. Update this shared demonstration context only if the overall sequence changes.
3. Update the live script only when actions or talking points change.
4. Update the detailed flow demonstration only when presenter evidence or recovery steps change.
5. Update the slide outline only when a slide claim changes.

