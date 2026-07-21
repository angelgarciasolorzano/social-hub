export interface TrustedDevice {
  id: number;
  name: string | null;
  userAgent: string | null;
  browser: string | null;
  osName: string | null;
  osVersion: string | null;
  isMobile: boolean;
  ip: string | null;
  lastUsedAt: string | null;
  expiresAt: string;
  isActive: boolean;
}
