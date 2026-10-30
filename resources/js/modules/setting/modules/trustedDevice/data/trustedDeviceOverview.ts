import { Eye, Pencil, Plus, RotateCw, Trash2 } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import { trustedDeviceRecommendations } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceRecommendations";
import type { TrustedDevice } from "@/modules/setting/modules/trustedDevice/types/trustedDevice";

export const trustedDeviceRecommendationsPreview = trustedDeviceRecommendations.slice(0, 3);

export const trustedDeviceRowActionKey = {
  viewDevice: "view-device",
  renameDevice: "rename-device",
  renewTrust: "renew-trust",
  revokeDevice: "revoke-device",
  reactivate: "reactivate",
  forceDestroy: "force-destroy",
} as const;

export const trustedDeviceGlobalShortcuts = {
  addDevice: { sequence: ["G", "N"], description: "setting/trusted-devices.shortcuts.add_device" },
  activity: { sequence: ["G", "A"], description: "setting/trusted-devices.shortcuts.activity" },
  recommendations: {
    sequence: ["G", "R"],
    description: "setting/trusted-devices.shortcuts.recommendations",
  },
  summary: { sequence: ["G", "S"], description: "setting/trusted-devices.shortcuts.summary" },
  revokeAll: { sequence: ["G", "V"], description: "setting/trusted-devices.shortcuts.revoke_all" },
} as const;

type TrustedDeviceRowActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

type TrustedDeviceRowActionShortcut = readonly ["F", "A" | "D" | "E" | "T" | "V" | "X"];

interface TrustedDeviceRowAction {
  key: TrustedDeviceRowActionKey;
  shortcut: TrustedDeviceRowActionShortcut;
  icon: LucideIcon;
  label: string;
  className?: string;
  iconClassName?: string;
  isEnabled: (device: TrustedDevice) => boolean;
}

interface TrustedDeviceRowActionGroup {
  label?: string;
  actions: TrustedDeviceRowAction[];
}

const isRevoked = (device: TrustedDevice): boolean => device.deletedAt !== null;

export const trustedDeviceRowActions: TrustedDeviceRowActionGroup[] = [
  {
    label: "setting/trusted-devices.row_actions.menu",
    actions: [
      {
        key: trustedDeviceRowActionKey.viewDevice,
        shortcut: ["F", "D"],
        icon: Eye,
        label: "setting/trusted-devices.row_actions.view",
        isEnabled: () => true,
      },
      {
        key: trustedDeviceRowActionKey.renameDevice,
        shortcut: ["F", "E"],
        icon: Pencil,
        label: "setting/trusted-devices.row_actions.rename",
        isEnabled: (device) => !isRevoked(device),
      },
      {
        key: trustedDeviceRowActionKey.renewTrust,
        shortcut: ["F", "T"],
        icon: RotateCw,
        label: "setting/trusted-devices.row_actions.renew",
        isEnabled: (device) => !isRevoked(device),
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceRowActionKey.reactivate,
        shortcut: ["F", "A"],
        icon: RotateCw,
        label: "setting/trusted-devices.row_actions.reactivate",
        isEnabled: isRevoked,
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceRowActionKey.revokeDevice,
        shortcut: ["F", "V"],
        icon: Trash2,
        label: "setting/trusted-devices.row_actions.revoke",
        isEnabled: (device) => !isRevoked(device),
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
      {
        key: trustedDeviceRowActionKey.forceDestroy,
        shortcut: ["F", "X"],
        icon: Trash2,
        label: "setting/trusted-devices.row_actions.force_destroy",
        isEnabled: (device) => isRevoked(device),
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];

export const trustedDeviceDialogKind = {
  addDevice: "add-device",
  deviceAlreadyRegistered: "device-already-registered",
  deviceExpired: "device-expired",
  deviceRevoked: "device-revoked",
  keyboardShortcuts: "keyboard-shortcuts",
  recommendations: "recommendations",
  summary: "summary",
  revokeAll: "revoke-all",
  activity: "activity",
} as const;

export type TrustedDeviceDialogKind =
  (typeof trustedDeviceDialogKind)[keyof typeof trustedDeviceDialogKind];

export type TrustedDeviceAddDeviceDialogKind = Exclude<
  TrustedDeviceDialogKind,
  | typeof trustedDeviceDialogKind.keyboardShortcuts
  | typeof trustedDeviceDialogKind.recommendations
  | typeof trustedDeviceDialogKind.summary
  | typeof trustedDeviceDialogKind.revokeAll
  | typeof trustedDeviceDialogKind.activity
>;

export type TrustedDeviceAdminActionKey =
  typeof trustedDeviceDialogKind.addDevice | typeof trustedDeviceDialogKind.revokeAll;

interface TrustedDeviceTitleAction {
  key: TrustedDeviceAdminActionKey;
  label: string;
  icon: LucideIcon;
  iconClassName?: string;
  className?: string;
}

interface TrustedDeviceTitleActionGroup {
  label?: string;
  actions: TrustedDeviceTitleAction[];
}

export const trustedDeviceTitleActions: TrustedDeviceTitleActionGroup[] = [
  {
    actions: [
      {
        key: trustedDeviceDialogKind.addDevice,
        icon: Plus,
        label: "setting/trusted-devices.header_actions.add",
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceDialogKind.revokeAll,
        icon: Trash2,
        label: "setting/trusted-devices.header_actions.revoke_all",
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];
