export const AnnouncementType = {
  CRITICAL: "critical",
  INFO: "info",
  WARNING: "warning",
} as const;

export type AnnouncementTypeValues = (typeof AnnouncementType)[keyof typeof AnnouncementType];

export const AnnouncementDisplay = {
  DIALOG: "dialog",
  TOAST: "toast",
} as const;

export type AnnouncementDisplayValues =
  (typeof AnnouncementDisplay)[keyof typeof AnnouncementDisplay];

export interface Announcement {
  bodyHtml: string;
  buttonLabel: null | string;
  buttonUrl: null | string;
  display: AnnouncementDisplayValues;
  id: number;
  imageUrl: null | string;
  isDismissible: boolean;
  title: string;
  type: AnnouncementTypeValues;
}
