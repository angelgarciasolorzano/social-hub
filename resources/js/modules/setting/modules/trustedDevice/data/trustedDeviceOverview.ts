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
  addDevice: { sequence: ["G", "N"], description: "Agregar un dispositivo de confianza" },
  activity: { sequence: ["G", "A"], description: "Abrir toda la actividad" },
  recommendations: { sequence: ["G", "R"], description: "Abrir recomendaciones" },
  summary: { sequence: ["G", "S"], description: "Abrir el resumen de dispositivos" },
  revokeAll: { sequence: ["G", "V"], description: "Revocar todos los dispositivos" },
} as const;

type TrustedDeviceRowActionKey =
  (typeof trustedDeviceRowActionKey)[keyof typeof trustedDeviceRowActionKey];

type TrustedDeviceRowActionShortcut = readonly ["G", "A" | "D" | "E" | "T" | "V" | "X"];

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
    label: "Acciones del dispositivo",
    actions: [
      {
        key: trustedDeviceRowActionKey.viewDevice,
        shortcut: ["G", "D"],
        icon: Eye,
        label: "Ver dispositivo",
        isEnabled: () => true,
      },
      {
        key: trustedDeviceRowActionKey.renameDevice,
        shortcut: ["G", "E"],
        icon: Pencil,
        label: "Renombrar dispositivo",
        isEnabled: (device) => !isRevoked(device),
      },
      {
        key: trustedDeviceRowActionKey.renewTrust,
        shortcut: ["G", "T"],
        icon: RotateCw,
        label: "Renovar confianza",
        isEnabled: (device) => !isRevoked(device),
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceRowActionKey.reactivate,
        shortcut: ["G", "A"],
        icon: RotateCw,
        label: "Reactivar",
        isEnabled: isRevoked,
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceRowActionKey.revokeDevice,
        shortcut: ["G", "V"],
        icon: Trash2,
        label: "Revocar dispositivo",
        isEnabled: (device) => !isRevoked(device),
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
      {
        key: trustedDeviceRowActionKey.forceDestroy,
        shortcut: ["G", "X"],
        icon: Trash2,
        label: "Eliminar definitivamente",
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
        label: "Agregar dispositivo",
      },
    ],
  },
  {
    actions: [
      {
        key: trustedDeviceDialogKind.revokeAll,
        icon: Trash2,
        label: "Revocar todos",
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];
