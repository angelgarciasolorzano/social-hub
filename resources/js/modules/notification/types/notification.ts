export type NotificationType =
  "comment_on_post" | "comment_reply" | "likes_grouped" | (string & {});

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
