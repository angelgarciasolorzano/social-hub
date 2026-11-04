import type { Notification } from "../types/notification";

/**
 * Excludes notifications that were removed from the live store.
 *
 * @param notifications - Notifications to filter.
 * @param removedNotificationIds - IDs to exclude.
 * @returns Notifications that have not been removed.
 */
export function excludeRemovedNotifications(
  notifications: readonly Notification[],
  removedNotificationIds: readonly string[] | undefined,
): Notification[] {
  if (removedNotificationIds === undefined || removedNotificationIds.length === 0) {
    return [...notifications];
  }

  const removedNotificationIdSet = new Set(removedNotificationIds);

  return notifications.filter((notification) => !removedNotificationIdSet.has(notification.id));
}
