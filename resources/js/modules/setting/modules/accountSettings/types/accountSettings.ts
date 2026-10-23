import type { SharedData } from "@/shared/types";

/** Mirrors the backend AccountStatus enum; read-only for the account owner. */
export type AccountStatus = "active" | "suspended";

export interface AccountSettings {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  preferredLocale: "en" | "es";
  biography: string | null;
  createdAt: string | null;
  lastLoginAt: string | null;
  status: AccountStatus;
}

export interface AccountSettingsPageProps extends SharedData {
  accountSettings: AccountSettings;
}
