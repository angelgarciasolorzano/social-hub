import { Calendar, Laptop, Monitor, RefreshCw, Smartphone, Trash2, Users } from "lucide-react";
import type { LucideIcon } from "lucide-react";

import type { IconColorVariant } from "@/shared/lib/styling";

export interface TrustedDeviceRecommendation {
  description: string;
  icon: LucideIcon;
  iconColor: IconColorVariant;
  title: string;
}

export const trustedDeviceRecommendations: TrustedDeviceRecommendation[] = [
  {
    description: "setting/trusted-devices.recommendations.review_periodically.description",
    icon: Calendar,
    iconColor: "blue",
    title: "setting/trusted-devices.recommendations.review_periodically.title",
  },
  {
    description: "setting/trusted-devices.recommendations.avoid_shared.description",
    icon: Users,
    iconColor: "green",
    title: "setting/trusted-devices.recommendations.avoid_shared.title",
  },
  {
    description: "setting/trusted-devices.recommendations.revoke_unknown.description",
    icon: Trash2,
    iconColor: "orange",
    title: "setting/trusted-devices.recommendations.revoke_unknown.title",
  },
  {
    description: "setting/trusted-devices.recommendations.keep_updated.description",
    icon: RefreshCw,
    iconColor: "purple",
    title: "setting/trusted-devices.recommendations.keep_updated.title",
  },
];

export interface TrustedDevicePreviewItem {
  icon: LucideIcon;
  iconVariant: IconColorVariant;
  name: string;
  subtitle: string;
}

export interface TrustedDevicePreviewBadge {
  label: string;
  variant: IconColorVariant;
}

export interface TrustedDevicePreviewRow {
  badge: TrustedDevicePreviewBadge;
  device: TrustedDevicePreviewItem;
}

export const trustedDevicePreviewRows: readonly TrustedDevicePreviewRow[] = [
  {
    badge: { label: "setting/trusted-devices.recommendations.preview.trusted", variant: "green" },
    device: {
      icon: Laptop,
      iconVariant: "blue",
      name: "MacBook Pro",
      subtitle: "setting/trusted-devices.recommendations.preview.desktop",
    },
  },
  {
    badge: { label: "setting/trusted-devices.recommendations.preview.trusted", variant: "green" },
    device: {
      icon: Smartphone,
      iconVariant: "green",
      name: "iPhone 14",
      subtitle: "setting/trusted-devices.recommendations.preview.mobile",
    },
  },
  {
    badge: { label: "setting/trusted-devices.recommendations.preview.revoke", variant: "red" },
    device: {
      icon: Monitor,
      iconVariant: "red",
      name: "Windows PC",
      subtitle: "setting/trusted-devices.recommendations.preview.desktop",
    },
  },
];

export const trustedDevicePreviewAlert = {
  body: "setting/trusted-devices.recommendations.preview.footer_description",
  title: "setting/trusted-devices.recommendations.preview.footer_title",
} as const;
