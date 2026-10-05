import type { SharedData } from "@/shared/types";

export interface AccountSettings {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  preferredLocale: "en" | "es";
  biography: string | null;
  createdAt: string | null;
  lastLoginAt: string | null;
}

export interface AccountSettingsPageProps extends SharedData {
  accountSettings: AccountSettings;
}
