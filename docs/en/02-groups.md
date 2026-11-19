# Groups

## What it is

A group joins devices (for example “Home”, “Work”, “Clients”) and gives them an icon and a colour.

## How it works

1. Create a group: name, icon, colour.
2. Add devices to it in the “Devices” section.
3. The group colour and icon are shown on the main page and in reports.
4. When a group is deleted, its devices are moved to a hidden group, not deleted.

## Add Group

- **Group name.**
- **Icon** — an emoji; the button opens the icon picker window.
- **Group color.**
- The “Add” button.

## Existing Groups

Each group card shows the icon, name, color and number of devices. The ✎ button opens the edit window:

- change the name, icon and color;
- delete the group.

When a group is renamed, its devices stay in it, and group filters in notification rules switch to the new name.

## Deleting a group

The panel asks for confirmation first. Devices of the deleted group are not lost: they are moved to the service group `__orphaned__`, which is not shown in the group list. You can bring them back by moving them to a group in the “Devices” section.

The confirmation text in the panel says the same: devices are not deleted but moved to a hidden group.

Group actions are written to the “Event log” and can trigger notifications: “Group created”, “Group renamed”, “Group deleted”.
