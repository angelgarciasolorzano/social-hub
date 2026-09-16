export interface TrustedDevicePreview {
  browser: string;
  browserVersion: string;
  osName: string;
  userAgent: string | null;
  isMobile: boolean;
  lastUsedAt: string;
  expiresAt: string;
}
