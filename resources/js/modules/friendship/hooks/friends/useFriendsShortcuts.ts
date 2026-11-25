import { useHotkey } from "@tanstack/react-hotkeys";

import type { FriendshipListTab } from "../../types/friendshipList";

interface UseFriendsShortcutsOptions {
  enabled: boolean;
  filterInput: HTMLInputElement | null;
  onChangeTab: (tab: FriendshipListTab) => void;
  onFocusFilter: () => void;
  onOpenHelp: () => void;
  onToggleSort: () => void;
}

/**
 * The keyboard shortcuts of the friends page: `/` focuses the name filter, `1`, `2` and `3` open the
 * friends, received and sent tabs, `o` switches between the most recent and the name sort, and
 * `Shift+?` opens the help. They are ignored while typing in a field; `Escape` leaves the filter so
 * they work again.
 */
export function useFriendsShortcuts({
  enabled,
  filterInput,
  onChangeTab,
  onFocusFilter,
  onOpenHelp,
  onToggleSort,
}: UseFriendsShortcutsOptions): void {
  const shortcutOptions = {
    enabled,
    ignoreInputs: true,
    preventDefault: true,
    stopPropagation: false,
  };

  useHotkey("/", whenNothingIsOpen(onFocusFilter), shortcutOptions);

  useHotkey(
    "1",
    whenNothingIsOpen(() => {
      onChangeTab("friends");
    }),
    shortcutOptions,
  );

  useHotkey(
    "2",
    whenNothingIsOpen(() => {
      onChangeTab("received");
    }),
    shortcutOptions,
  );

  useHotkey(
    "3",
    whenNothingIsOpen(() => {
      onChangeTab("sent");
    }),
    shortcutOptions,
  );

  useHotkey("O", whenNothingIsOpen(onToggleSort), shortcutOptions);

  useHotkey({ key: "?", shift: true }, whenNothingIsOpen(onOpenHelp), shortcutOptions);

  useHotkey(
    "Escape",
    () => {
      filterInput?.blur();
    },
    {
      enabled: enabled && filterInput !== null,
      ignoreInputs: false,
      preventDefault: false,
      stopPropagation: false,
      target: filterInput,
    },
  );
}

/**
 * Runs the action unless a dialog, a menu or a select list is open, so a shortcut never acts behind
 * them. Only `data-state="open"` counts: a dialog that is still animating out stays in the DOM.
 */
function whenNothingIsOpen(action: () => void): () => void {
  return (): void => {
    const openOverlay = document.querySelector(
      '[role="dialog"][data-state="open"], [role="menu"][data-state="open"], [role="listbox"][data-state="open"]',
    );

    if (openOverlay !== null) return;

    action();
  };
}
