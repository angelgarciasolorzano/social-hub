import {
  ChartNoAxesGantt,
  CircleX,
  LockKeyhole,
  MonitorSmartphone,
  RefreshCw,
  Repeat,
  RotateCcw,
  ShieldOff,
  TableOfContents,
} from "lucide-react";

import type { OptionCardItem } from "../components/ui/OptionCard";

/**
 * Titles, descriptions and labels of these lists are translation keys of `setting/two-factor`; the view translates them.
 */
export const twoFactorSecurityOptionsKey = {
  backupCodes: "backup-codes",
  regenerateCodes: "regenerate-codes",
  disable2FA: "disable-2fa",
} as const;

export type TwoFactorSecurityOptionKey =
  (typeof twoFactorSecurityOptionsKey)[keyof typeof twoFactorSecurityOptionsKey];

export const twoFactorSecurityOptions: OptionCardItem<TwoFactorSecurityOptionKey>[] = [
  {
    key: twoFactorSecurityOptionsKey.backupCodes,
    title: "setting/two-factor.security_options.backup_codes.title",
    description: "setting/two-factor.security_options.backup_codes.description",
    icon: TableOfContents,
    iconColor: "violet",
  },
  {
    key: twoFactorSecurityOptionsKey.regenerateCodes,
    title: "setting/two-factor.security_options.regenerate_codes.title",
    description: "setting/two-factor.security_options.regenerate_codes.description",
    icon: Repeat,
    iconColor: "violet",
  },
  {
    key: twoFactorSecurityOptionsKey.disable2FA,
    title: "setting/two-factor.security_options.disable.title",
    description: "setting/two-factor.security_options.disable.description",
    icon: ShieldOff,
    iconColor: "red",
  },
];

type TwoFactorSafetyTip = Pick<OptionCardItem, "key" | "title" | "description" | "icon">;

export const twoFactorSafetyTips: TwoFactorSafetyTip[] = [
  {
    key: "save-backup-codes",
    icon: LockKeyhole,
    title: "setting/two-factor.safety_tips.save_backup_codes.title",
    description: "setting/two-factor.safety_tips.save_backup_codes.description",
  },
  {
    key: "use-devices-trust",
    icon: MonitorSmartphone,
    title: "setting/two-factor.safety_tips.use_trusted_devices.title",
    description: "setting/two-factor.safety_tips.use_trusted_devices.description",
  },
  {
    key: "keep-your-app-updated",
    icon: RotateCcw,
    title: "setting/two-factor.safety_tips.keep_app_updated.title",
    description: "setting/two-factor.safety_tips.keep_app_updated.description",
  },
];

export const twoFactorDeviceSectionActionKey = {
  addDevice: "add-device",
  deviceAlreadyRegistered: "device-already-registered",
  revokeAllDevices: "revoke-all-devices",
} as const;

export type TwoFactorDeviceSectionActionKey =
  (typeof twoFactorDeviceSectionActionKey)[keyof typeof twoFactorDeviceSectionActionKey];

export const twoFactorManageActionKey = {
  viewCodes: "view-codes",
  regenerateCodes: "regenerate-codes",
  viewDevices: "view-devices",
  disable2FA: "disable-2fa",
} as const;

export type TwoFactorManageActionKey =
  (typeof twoFactorManageActionKey)[keyof typeof twoFactorManageActionKey];

export type TwoFactorManageAction = Pick<
  OptionCardItem<TwoFactorManageActionKey>,
  "key" | "icon"
> & {
  label: string;
  className?: string;
  iconClassName?: string;
};

export interface ManageActionGroup {
  label?: string;
  actions: TwoFactorManageAction[];
}

export const twoFactorManageActions: ManageActionGroup[] = [
  {
    actions: [
      {
        key: twoFactorManageActionKey.viewCodes,
        icon: ChartNoAxesGantt,
        label: "setting/two-factor.manage_actions.view_codes",
      },
      {
        key: twoFactorManageActionKey.regenerateCodes,
        icon: RefreshCw,
        label: "setting/two-factor.manage_actions.regenerate_codes",
      },
      {
        key: twoFactorManageActionKey.viewDevices,
        icon: MonitorSmartphone,
        label: "setting/two-factor.manage_actions.trusted_devices",
      },
    ],
  },
  {
    actions: [
      {
        key: twoFactorManageActionKey.disable2FA,
        icon: CircleX,
        label: "setting/two-factor.manage_actions.disable",
        className:
          "text-red-700 hover:bg-red-100/50 focus:bg-red-100/50 focus:text-red-700 dark:text-red-500 dark:hover:bg-red-800/20 dark:focus:bg-red-900/20 dark:focus:text-red-500",
        iconClassName: "text-red-600 dark:text-red-500",
      },
    ],
  },
];
