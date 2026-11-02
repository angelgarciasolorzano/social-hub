import type { LucideIcon } from "lucide-react";
import { Bell, Eye, FileText, Mail, Megaphone, Settings2, UserRound } from "lucide-react";

import type { IconColorVariant } from "@/shared/lib/styling";

/**
 * `title` and `description` are translation keys of `setting/account-settings`; the page translates them.
 */
export interface AccountPrivacyPrinciple {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

/**
 * `title` and `description` are translation keys of `setting/account-settings`; the page translates them.
 */
export interface AccountCommunicationChannel {
  description: string;
  enabled: boolean;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  id: "email" | "system" | "news";
  title: string;
}

export const privacyPrinciples: readonly AccountPrivacyPrinciple[] = [
  {
    description: "setting/account-settings.privacy_principles.account_data.description",
    icon: UserRound,
    iconColor: "blue",
    title: "setting/account-settings.privacy_principles.account_data.title",
  },
  {
    description: "setting/account-settings.privacy_principles.public_profile.description",
    icon: Eye,
    iconColor: "violet",
    title: "setting/account-settings.privacy_principles.public_profile.title",
  },
  {
    description: "setting/account-settings.privacy_principles.your_choice.description",
    icon: Settings2,
    iconColor: "amber",
    title: "setting/account-settings.privacy_principles.your_choice.title",
  },
  {
    description: "setting/account-settings.privacy_principles.account_options.description",
    icon: FileText,
    iconColor: "green",
    title: "setting/account-settings.privacy_principles.account_options.title",
  },
];

export const communicationChannels: readonly AccountCommunicationChannel[] = [
  {
    description: "setting/account-settings.communication.channels.email.description",
    enabled: true,
    icon: Mail,
    iconColor: "blue",
    id: "email",
    title: "setting/account-settings.communication.channels.email.title",
  },
  {
    description: "setting/account-settings.communication.channels.system.description",
    enabled: true,
    icon: Bell,
    iconColor: "green",
    id: "system",
    title: "setting/account-settings.communication.channels.system.title",
  },
  {
    description: "setting/account-settings.communication.channels.news.description",
    enabled: false,
    icon: Megaphone,
    iconColor: "violet",
    id: "news",
    title: "setting/account-settings.communication.channels.news.title",
  },
];
