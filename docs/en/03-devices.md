# Devices

## What it is

A device is a computer or server that activates against your KMS server. The name comes from the vlmcsd log.

## How it works

1. A device contacts the KMS server and its name appears in the log.
2. Until it is added to the panel it is shown as “unknown” on the main page.
3. Add it with a name, a group and a comment.
4. Then you can search, filter, edit and move it between groups.

## Add Device

- **Device name** — as written in the log.
- **Group** — one of the existing groups.
- **Comment** — optional, for example who owns the device.
- The “Add” button. A repeated name in the same group is rejected.

A device can also be added from the main page with the ➕ button in the unknown devices list.

## All Devices

- **Filter** by group (all groups or a specific one).
- **Sort:** by name, by group, by added date (oldest first or newest first).
- The “Showing N of M” counter.
- Each device has edit (name, group, comment) and delete buttons.

## Bulk move

1. Tick devices or press “Select all”.
2. Choose the target group in the move list.
3. Press “Move” and confirm. The “Selected: N” counter shows how many devices are ticked.

Moving keeps device comments and added dates.

## Log and notifications

Adding, editing, moving and deleting are written to the “Event log”. Notification events exist for them: “Device added”, “Device edited”, “Devices moved to a group”, “Device deleted”. Devices that appear in the log but are not added to the panel are tracked by a separate event, “New device in the log” (background check).
