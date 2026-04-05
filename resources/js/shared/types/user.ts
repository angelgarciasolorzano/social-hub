export interface Auth {
  user: User;
}

export interface User {
  [key: string]: unknown;
  coverImage?: string;
  created_at: string;
  email: string;
  email_verified_at: string | null;
  id: number;
  mustVerifyEmail?: boolean;
  name: string;
  profilePicture?: string;
  requiresConfirmation?: boolean;
  sessionStatus?: string;
  twoFactorEnabled?: boolean;
  updated_at: string;
}
