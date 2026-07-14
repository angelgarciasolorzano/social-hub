export type TrustedDevice = {
  id: number;
  name: string | null;
  userAgent: string | null;
  ip: string | null;
  lastUsedAt: string | null;
  expiresAt: string;
  isActive: boolean;
};
