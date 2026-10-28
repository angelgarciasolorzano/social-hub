/**
 * Announcements the browser closed without the server: a guest's closes live in `localStorage` until they are
 * synced after signing in, and closes of non-dismissible announcements last only for the session.
 */
const GUEST_DISMISSED_KEY = "social-hub.announcements.dismissed";
const SESSION_DISMISSED_KEY = "social-hub.announcements.dismissed-this-session";

type StorageArea = "local" | "session";

/**
 * Read the announcements a guest closed in this browser.
 *
 * @returns The stored announcement ids, or an empty list when the storage is unavailable or holds an invalid value.
 */
export function readGuestDismissedIds(): number[] {
  return readIds("local", GUEST_DISMISSED_KEY);
}

/**
 * Remember that a guest closed an announcement in this browser.
 *
 * @param announcementId Id of the closed announcement.
 * @returns Whether the close was stored.
 */
export function addGuestDismissedId(announcementId: number): boolean {
  return addId("local", GUEST_DISMISSED_KEY, announcementId);
}

/**
 * Forget the guest's closes the server has stored, keeping any that were not sent yet.
 *
 * @param announcementIds Ids that were sent to the server.
 * @returns Whether the stored list was updated.
 */
export function removeGuestDismissedIds(announcementIds: number[]): boolean {
  const pendingIds = readGuestDismissedIds().filter(
    (storedId) => !announcementIds.includes(storedId),
  );

  try {
    const storage = storageOf("local");

    if (pendingIds.length === 0) {
      storage?.removeItem(GUEST_DISMISSED_KEY);
    } else {
      storage?.setItem(GUEST_DISMISSED_KEY, JSON.stringify(pendingIds));
    }

    return storage !== null;
  } catch {
    return false;
  }
}

/**
 * Read the non-dismissible announcements closed during this browser session.
 *
 * @returns The stored announcement ids, or an empty list when the storage is unavailable or holds an invalid value.
 */
export function readSessionDismissedIds(): number[] {
  return readIds("session", SESSION_DISMISSED_KEY);
}

/**
 * Hide a non-dismissible announcement until the browser session ends.
 *
 * @param announcementId Id of the closed announcement.
 * @returns Whether the close was stored.
 */
export function addSessionDismissedId(announcementId: number): boolean {
  return addId("session", SESSION_DISMISSED_KEY, announcementId);
}

/**
 * Read a list of announcement ids, keeping only positive integers.
 *
 * @param storageArea Storage that holds the list.
 * @param key Storage key of the list.
 * @returns The ids, or an empty list when the storage is unavailable or the value is not a list.
 */
function readIds(storageArea: StorageArea, key: string): number[] {
  try {
    const storedValue = storageOf(storageArea)?.getItem(key) ?? null;

    if (storedValue === null) {
      return [];
    }

    const parsedValue: unknown = JSON.parse(storedValue);

    return Array.isArray(parsedValue) ? parsedValue.filter(isAnnouncementId) : [];
  } catch {
    return [];
  }
}

/**
 * Add an id to a stored list unless it is already there.
 *
 * @param storageArea Storage that holds the list.
 * @param key Storage key of the list.
 * @param announcementId Id to add.
 * @returns Whether the id is in the stored list afterwards.
 */
function addId(storageArea: StorageArea, key: string, announcementId: number): boolean {
  const storedIds = readIds(storageArea, key);

  if (storedIds.includes(announcementId)) {
    return true;
  }

  try {
    const storage = storageOf(storageArea);

    storage?.setItem(key, JSON.stringify([...storedIds, announcementId]));

    return storage !== null;
  } catch {
    return false;
  }
}

/**
 * Get the browser storage; reading `localStorage` can throw when the browser blocks it.
 *
 * @param storageArea Which storage to get.
 * @returns The storage, or null outside the browser.
 */
function storageOf(storageArea: StorageArea): null | Storage {
  if (typeof window === "undefined") {
    return null;
  }

  return storageArea === "local" ? window.localStorage : window.sessionStorage;
}

/**
 * Check that a stored value is a valid announcement id.
 *
 * @param value Value read from the storage.
 * @returns Whether the value is a positive integer.
 */
function isAnnouncementId(value: unknown): value is number {
  return typeof value === "number" && Number.isInteger(value) && value > 0;
}
