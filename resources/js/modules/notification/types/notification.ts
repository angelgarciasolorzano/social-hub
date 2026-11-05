const supportedNotificationTypes = ["comment_on_post", "comment_reply", "likes_grouped"] as const;

export type NotificationType = (typeof supportedNotificationTypes)[number];

export function isNotificationType(value: unknown): value is NotificationType {
  return (
    typeof value === "string" &&
    supportedNotificationTypes.some((notificationType) => notificationType === value)
  );
}

export interface NotificationActor {
  id: number;
  name: string;
}

export interface NotificationTarget {
  postId: number | null;
  commentId: number | null;
}

export interface Notification {
  id: string;
  type: NotificationType;
  summary: string;
  actors: NotificationActor[];
  target: NotificationTarget;
  readAt: string | null;
  createdAt: string | null;
}
