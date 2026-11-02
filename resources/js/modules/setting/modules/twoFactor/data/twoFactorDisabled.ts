import type { ComponentType, SVGProps } from "react";

import {
  Authy,
  Google,
  Microsoft,
  OnePasswordDark,
  OnePasswordLight,
} from "@ridemountainpig/svgl-react";
import { KeyRound, LockKeyhole, MonitorSmartphone, ShieldCheck, Star } from "lucide-react";

import type { OptionCardItem } from "../components/ui/OptionCard";
import type { Step } from "../components/ui/Timeline";

/**
 * Texts of these lists are translation keys of `setting/two-factor`; the views translate them.
 */
export const twoFactorBenefits: OptionCardItem[] = [
  {
    key: "security",
    title: "setting/two-factor.benefits.security.title",
    description: "setting/two-factor.benefits.security.description",
    icon: LockKeyhole,
    iconColor: "green",
  },
  {
    key: "verification",
    title: "setting/two-factor.benefits.verification.title",
    description: "setting/two-factor.benefits.verification.description",
    icon: ShieldCheck,
    iconColor: "blue",
  },
  {
    key: "standard",
    title: "setting/two-factor.benefits.standard.title",
    description: "setting/two-factor.benefits.standard.description",
    icon: Star,
    iconColor: "purple",
  },
];

type TwoFactorImportantDetail = OptionCardItem;

export const twoFactorImportantDetails: TwoFactorImportantDetail[] = [
  {
    key: "standard",
    title: "setting/two-factor.important_details.standard.title",
    description: "setting/two-factor.important_details.standard.description",
    icon: KeyRound,
    iconColor: "green",
  },
  {
    key: "backup-codes",
    title: "setting/two-factor.important_details.backup_codes.title",
    description: "setting/two-factor.important_details.backup_codes.description",
    icon: MonitorSmartphone,
    iconColor: "yellow",
  },
  {
    key: "devices-trusted",
    title: "setting/two-factor.important_details.trusted_devices.title",
    description: "setting/two-factor.important_details.trusted_devices.description",
    icon: ShieldCheck,
    iconColor: "blue",
  },
  {
    key: "support",
    title: "setting/two-factor.important_details.support.title",
    description: "setting/two-factor.important_details.support.description",
    icon: LockKeyhole,
    iconColor: "purple",
  },
];

type TwoFactorOperationStep = Step;

export const twoFactorOperationSteps: TwoFactorOperationStep[] = [
  {
    number: 1,
    title: "setting/two-factor.operation_steps.scan.title",
    description: "setting/two-factor.operation_steps.scan.description",
  },
  {
    number: 2,
    title: "setting/two-factor.operation_steps.get_code.title",
    description: "setting/two-factor.operation_steps.get_code.description",
  },
  {
    number: 3,
    title: "setting/two-factor.operation_steps.enter_code.title",
    description: "setting/two-factor.operation_steps.enter_code.description",
  },
];

type TwoFactorRequirement = Pick<OptionCardItem, "description" | "key">;

export const twoFactorRequirements: TwoFactorRequirement[] = [
  {
    key: "totp-app",
    description: "setting/two-factor.requirements.totp_app",
  },
  {
    key: "mobile-access",
    description: "setting/two-factor.requirements.mobile_access",
  },
  {
    key: "internet-connection",
    description: "setting/two-factor.requirements.internet_connection",
  },
];

interface TwoFactorRecommendedApp {
  key: string;
  name: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  iconDark?: ComponentType<SVGProps<SVGSVGElement>>;
}

export const twoFactorRecommendedApps: TwoFactorRecommendedApp[] = [
  {
    key: "google-authenticator",
    name: "Google Authenticator",
    icon: Google,
  },
  {
    key: "authy",
    name: "Authy",
    icon: Authy,
  },
  {
    key: "one-password",
    name: "1Password",
    icon: OnePasswordLight,
    iconDark: OnePasswordDark,
  },
  {
    key: "microsoft-authenticator",
    name: "Microsoft Authenticator",
    icon: Microsoft,
  },
];
