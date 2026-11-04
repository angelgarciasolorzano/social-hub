import type { JSX } from "react";
import { useCallback, useEffect, useState } from "react";

import { router } from "@inertiajs/react";

import { useEchoNotification } from "@laravel/echo-react";
import { toast } from "sonner";

import { useNotificationStore } from "../stores/notificationStore";
import type { Notification } from "../types/notification";

type NotificationBroadcastData = Pick<Notification, "actors" | "summary" | "target">;

interface NotificationBroadcastEnvelope {
  id: string;
  type: Notification["type"];
}

interface NotificationBroadcastDeletion extends NotificationBroadcastEnvelope {
  deleted: true;
}

type NotificationBroadcastPayload = NotificationBroadcastEnvelope &
  (NotificationBroadcastData | { deleted: true });

interface NotificationRealtimeListenerProps {
  initialUnreadNotificationsCount: number | undefined;
  initialUserId: number | null;
}

interface AuthenticatedNotificationListenerProps {
  unreadNotificationsCount: number | undefined;
  userId: number;
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === "object" && value !== null;
}

function isNotificationDeletionPayload(
  payload: NotificationBroadcastPayload,
): payload is NotificationBroadcastDeletion {
  return "deleted" in payload;
}

function userIdFromPageProps(pageProps: Record<string, unknown>): number | null {
  const auth = pageProps["auth"];

  if (!isRecord(auth) || !isRecord(auth["user"])) {
    return null;
  }

  const userId = auth["user"]["id"];

  return typeof userId === "number" ? userId : null;
}

function unreadCountFromPageProps(pageProps: Record<string, unknown>): number | undefined {
  const unreadCount = pageProps["unreadNotificationsCount"];

  return typeof unreadCount === "number" ? unreadCount : undefined;
}

function NotificationRealtimeListener({
  initialUnreadNotificationsCount,
  initialUserId,
}: NotificationRealtimeListenerProps): JSX.Element | null {
  const [userId, setUserId] = useState<number | null>(initialUserId);
  const [unreadNotificationsCount, setUnreadNotificationsCount] = useState<number | undefined>(
    initialUnreadNotificationsCount,
  );

  useEffect(() => {
    return router.on("navigate", (event) => {
      const pageProps = event.detail.page.props as Record<string, unknown>;

      setUserId(userIdFromPageProps(pageProps));
      setUnreadNotificationsCount(unreadCountFromPageProps(pageProps));
    });
  }, []);

  if (userId === null) {
    return null;
  }

  return (
    <AuthenticatedNotificationListener
      key={userId}
      unreadNotificationsCount={unreadNotificationsCount}
      userId={userId}
    />
  );
}

function AuthenticatedNotificationListener({
  unreadNotificationsCount,
  userId,
}: AuthenticatedNotificationListenerProps): null {
  const initializeForUser = useNotificationStore((state) => state.initializeForUser);
  const receiveNotification = useNotificationStore((state) => state.receiveNotification);
  const removeNotification = useNotificationStore((state) => state.removeNotification);

  useEffect(() => {
    initializeForUser(userId, unreadNotificationsCount);
  }, [initializeForUser, unreadNotificationsCount, userId]);

  const handleNotification = useCallback<(payload: NotificationBroadcastPayload) => void>(
    (payload) => {
      if (isNotificationDeletionPayload(payload)) {
        if (payload.type === "likes_grouped") {
          removeNotification(payload.id);
        }

        return;
      }

      const notification: Notification = {
        id: payload.id,
        type: payload.type,
        summary: payload.summary,
        actors: payload.actors,
        target: payload.target,
        readAt: null,
        createdAt: new Date().toISOString(),
      };

      if (receiveNotification(notification)) {
        toast.info(notification.summary);
      }
    },
    [receiveNotification, removeNotification],
  );

  useEchoNotification<NotificationBroadcastData | { deleted: true }>(
    `users.${userId}.notifications`,
    handleNotification,
    undefined,
    [handleNotification],
  );

  return null;
}

export default NotificationRealtimeListener;
