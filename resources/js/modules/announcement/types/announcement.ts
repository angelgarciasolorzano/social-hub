export const AnnouncementType = {
  CRITICAL: "critical",
  INFO: "info",
  WARNING: "warning",
} as const;

export type AnnouncementTypeValues = (typeof AnnouncementType)[keyof typeof AnnouncementType];

export interface Announcement {
  bodyHtml: string;
  buttonLabel: null | string;
  buttonUrl: null | string;
  id: number;
  imageUrl: null | string;
  isDismissible: boolean;
  title: string;
  type: AnnouncementTypeValues;
}
