import { create } from "zustand";

import type { Notification } from "../types/notification";

interface NotificationStoreState {
  authenticatedUserId: number | null;
  notifications: Notification[];
  unreadCount: number | null;
  initializeForUser: (userId: number, unreadCount: number | undefined) => void;
  receiveNotification: (notification: Notification) => boolean;
  markNotificationAsRead: (notificationId: string, fallbackUnreadCount: number) => void;
  markAllNotificationsAsRead: (readAt: string) => void;
}

function notificationContentIsEqual(
  currentNotification: Notification,
  incomingNotification: Notification,
): boolean {
  return (
    currentNotification.type === incomingNotification.type &&
    currentNotification.summary === incomingNotification.summary &&
    JSON.stringify(currentNotification.actors) === JSON.stringify(incomingNotification.actors) &&
    currentNotification.target.postId === incomingNotification.target.postId &&
    currentNotification.target.commentId === incomingNotification.target.commentId
  );
}

export const useNotificationStore = create<NotificationStoreState>()((set) => ({
  authenticatedUserId: null,
  notifications: [],
  unreadCount: null,

  initializeForUser: (userId, unreadCount) => {
    set((state) => {
      if (state.authenticatedUserId !== userId) {
        return {
          authenticatedUserId: userId,
          notifications: [],
          unreadCount: unreadCount ?? null,
        };
      }

      if (typeof unreadCount === "number" && unreadCount !== state.unreadCount) {
        return { unreadCount };
      }

      return state;
    });
  },

  receiveNotification: (notification) => {
    let hasChanged = false;

    set((state) => {
      const existingIndex = state.notifications.findIndex(
        (candidateNotification) => candidateNotification.id === notification.id,
      );

      const existingNotification = state.notifications[existingIndex];

      if (existingIndex === -1 || existingNotification === undefined) {
        hasChanged = true;

        return {
          notifications: [notification, ...state.notifications],
          unreadCount:
            state.unreadCount === null || notification.readAt !== null
              ? state.unreadCount
              : state.unreadCount + 1,
        };
      }

      const updatedNotification: Notification = {
        ...notification,
        createdAt: existingNotification.createdAt ?? notification.createdAt,
        readAt: existingNotification.readAt ?? notification.readAt,
      };

      hasChanged = !notificationContentIsEqual(existingNotification, updatedNotification);

      if (!hasChanged) {
        return state;
      }

      const notifications = [...state.notifications];
      notifications[existingIndex] = updatedNotification;

      const existingNotificationWasUnread = existingNotification.readAt === null;
      const updatedNotificationIsUnread = updatedNotification.readAt === null;
      const unreadCountDelta =
        Number(updatedNotificationIsUnread) - Number(existingNotificationWasUnread);

      return {
        notifications,
        unreadCount:
          state.unreadCount === null ? null : Math.max(0, state.unreadCount + unreadCountDelta),
      };
    });

    return hasChanged;
  },

  markNotificationAsRead: (notificationId, fallbackUnreadCount) => {
    set((state) => {
      const existingIndex = state.notifications.findIndex(
        (candidateNotification) => candidateNotification.id === notificationId,
      );

      const existingNotification = state.notifications[existingIndex];

      if (existingNotification !== undefined && existingNotification.readAt !== null) {
        return state;
      }

      const notifications = [...state.notifications];

      if (existingNotification !== undefined) {
        notifications[existingIndex] = {
          ...existingNotification,
          readAt: new Date().toISOString(),
        };
      }

      return {
        notifications,
        unreadCount:
          state.unreadCount === null
            ? Math.max(0, fallbackUnreadCount - 1)
            : Math.max(0, state.unreadCount - 1),
      };
    });
  },

  markAllNotificationsAsRead: (readAt) => {
    const readAtTimestamp = Date.parse(readAt);

    set((state) => {
      const notifications = state.notifications.map((notification) => {
        const notificationTimestamp =
          notification.createdAt === null
            ? Number.NEGATIVE_INFINITY
            : Date.parse(notification.createdAt);

        if (notification.readAt !== null || notificationTimestamp > readAtTimestamp) {
          return notification;
        }

        return { ...notification, readAt };
      });
      const unreadNotificationsAfterReadAll = notifications.filter(
        (notification) => notification.readAt === null,
      ).length;

      return {
        notifications,
        unreadCount: unreadNotificationsAfterReadAll,
      };
    });
  },
}));
