# Laboratories and Devices

Documentation home: [Documentation Index and Source-of-Truth Map](DOCUMENTATION_INDEX.md).

This is the canonical reference for the relationship between physical laboratories and managed attendance-panel devices.

## Concept

- A **Laboratory** is a physical room used by schedules and attendance sessions.
- A **Device** is the managed attendance panel assigned to that laboratory.
- A laboratory can have one managed device.
- A device cannot be assigned to more than one laboratory.

## Laboratory Management

Laboratory CRUD owns:

- Room name
- Physical location
- Description
- Active/inactive availability
- Schedule and attendance-room identity

Laboratory actions are Add, Edit, Activate/Deactivate, and Delete. Laboratory status describes whether the room is available; it is not the panel security switch.

## Device Management

Device CRUD owns:

- Laboratory assignment
- Unique device label
- Description
- Device-specific PIN
- Enabled/disabled access
- Live panel logout
- Device deletion

Disabling a device prevents its laboratory panel from authenticating. It does not deactivate or delete the laboratory itself.

Changing a device PIN affects only that laboratory's managed panel. The PIN is stored as a hash and is never displayed.

An active panel must be remotely logged out before its device can be deleted. Historical attendance and audit logs remain after device deletion.

## Fallback Access

The global fallback label and PIN exist only for laboratories without a managed device. Normal operation should create a device and use device-specific PIN management.

## Recommended Setup

1. Create the Laboratory.
2. Create a Device and assign it to that Laboratory.
3. Set the Device's initial PIN.
4. Keep both the Laboratory active and Device enabled.
5. Open Panel Login, select the Laboratory, and enter that Device's PIN.
6. Use Device actions later to change the PIN, disable access, edit assignment details, remotely log out, or delete the device.

## Attendance Panel Integration

- Panel Login resolves the managed device from the selected laboratory.
- A successful login opens the panel session using that device's label.
- A disabled managed device rejects login and never falls back to the global PIN.
- A laboratory without a managed device can still use the explicitly labeled fallback configuration.
- RFID check-in, temporary movement, checkout, Dismiss Class, face verification, and attendance logging continue through the same panel session after device authentication.
