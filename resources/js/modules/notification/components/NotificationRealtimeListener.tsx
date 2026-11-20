import type { JSX, RefObject } from "react";
import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { useEchoNotification } from "@laravel/echo-react";
import { toast } from "sonner";

import type { FriendshipState } from "@/modules/friendship/types/friendshipSearch";
import { reloadFriendshipDependentProps } from "@/modules/friendship/utils/reloadFriendshipDependentProps";

import { useNotificationStore } from "../stores/notificationStore";
import { isNotificationType } from "../types/notification";
import type { Notification, NotificationType } from "../types/notification";

type NotificationBroadcastData = Pick<Notification, "actors" | "summary" | "target">;

interface NotificationBroadcastEnvelope {
  id: string;
  type: string;
}

interface NotificationBroadcastDeletion extends NotificationBroadcastEnvelope {
  deleted: true;
}

type NotificationBroadcastPayload = NotificationBroadcastEnvelope &
  (NotificationBroadcastData | { deleted: true });

const FRIENDSHIP_STATE_OF_NOTIFICATION_TYPE: Partial<Record<NotificationType, FriendshipState>> = {
  friend_request_accepted: "friends",
  friend_request_received: "request_received",
};

interface NotificationRealtimeListenerProps {
  initialComponent: string;
  initialUnreadNotificationsCount: number | undefined;
  initialUserId: number | null;
}

interface AuthenticatedNotificationListenerProps {
  componentRef: RefObject<string>;
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
  initialComponent,
  initialUnreadNotificationsCount,
  initialUserId,
}: NotificationRealtimeListenerProps): JSX.Element | null {
  const componentRef = useRef<string>(initialComponent);

  const [userId, setUserId] = useState<number | null>(initialUserId);
  const [unreadNotificationsCount, setUnreadNotificationsCount] = useState<number | undefined>(
    initialUnreadNotificationsCount,
  );

  useEffect(() => {
    return router.on("navigate", (event) => {
      const pageProps = event.detail.page.props as Record<string, unknown>;

      componentRef.current = event.detail.page.component;

      setUserId(userIdFromPageProps(pageProps));
      setUnreadNotificationsCount(unreadCountFromPageProps(pageProps));
    });
  }, []);

  if (userId === null) {
    return null;
  }

  return (
    <AuthenticatedNotificationListener
      componentRef={componentRef}
      key={userId}
      unreadNotificationsCount={unreadNotificationsCount}
      userId={userId}
    />
  );
}

function AuthenticatedNotificationListener({
  componentRef,
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
      if (!isNotificationType(payload.type)) {
        return;
      }

      const friendshipState = FRIENDSHIP_STATE_OF_NOTIFICATION_TYPE[payload.type];

      if (isNotificationDeletionPayload(payload)) {
        if (payload.type === "likes_grouped" || payload.type === "friend_request_received") {
          removeNotification(payload.id);
        }

        if (friendshipState !== undefined) {
          reloadFriendshipDependentProps(componentRef.current, "none");
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

      if (friendshipState !== undefined) {
        reloadFriendshipDependentProps(componentRef.current, friendshipState);
      }
    },
    [componentRef, receiveNotification, removeNotification],
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
