import type { JSX } from "react";
import { useSyncExternalStore } from "react";

import type { RegisterableHotkey } from "@tanstack/react-hotkeys";
import { detectPlatform, formatForDisplay } from "@tanstack/react-hotkeys";
import { KeyboardIcon } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { useTranslation } from "@/shared/hooks";

import { cn } from "@/shared/lib/utils";

interface FriendsShortcut {
  descriptionKey?: string;
  hotkeys: RegisterableHotkey[];
  isWide?: boolean;
  titleKey: string;
}

const FRIENDS_SHORTCUTS: FriendsShortcut[] = [
  {
    descriptionKey: "friendship/friends.shortcuts.tabs_description",
    hotkeys: ["1", "2", "3"],
    isWide: true,
    titleKey: "friendship/friends.shortcuts.tabs",
  },
  { hotkeys: ["/"], titleKey: "friendship/friends.shortcuts.focus_filter" },
  { hotkeys: ["Escape"], titleKey: "friendship/friends.shortcuts.leave_filter" },
  { hotkeys: ["O"], titleKey: "friendship/friends.shortcuts.toggle_sort" },
  { hotkeys: [{ key: "?", shift: true }], titleKey: "friendship/friends.shortcuts.show_help" },
];

type Platform = ReturnType<typeof detectPlatform>;

interface FriendsKeyboardShortcutsDialogProps {
  onOpenChange: (open: boolean) => void;
  open: boolean;
}

function FriendsKeyboardShortcutsDialog(props: FriendsKeyboardShortcutsDialogProps): JSX.Element {
  const { onOpenChange, open } = props;

  const { t } = useTranslation();

  const subscribeToPlatformChanges = (): (() => void) => {
    return () => undefined;
  };

  const getServerPlatformSnapshot = (): Platform => {
    return "linux";
  };

  const platform = useSyncExternalStore(
    subscribeToPlatformChanges,
    detectPlatform,
    getServerPlatformSnapshot,
  );

  return (
    <Dialog onOpenChange={onOpenChange} open={open}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <KeyboardIcon aria-hidden="true" className="size-5 text-muted-foreground" />
            {t("friendship/friends.shortcuts.title")}
          </DialogTitle>

          <DialogDescription>{t("friendship/friends.shortcuts.description")}</DialogDescription>
        </DialogHeader>

        <ul className="grid grid-cols-2 gap-2.5">
          {FRIENDS_SHORTCUTS.map((friendsShortcut) => (
            <FriendsShortcutTile
              friendsShortcut={friendsShortcut}
              key={friendsShortcut.titleKey}
              platform={platform}
            />
          ))}
        </ul>

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">{t("friendship/friends.shortcuts.got_it")}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

interface FriendsShortcutTileProps {
  friendsShortcut: FriendsShortcut;
  platform: Platform;
}

function FriendsShortcutTile(props: FriendsShortcutTileProps): JSX.Element {
  const { friendsShortcut, platform } = props;

  const { t } = useTranslation();

  const { descriptionKey, hotkeys, isWide = false, titleKey } = friendsShortcut;

  const keyLabels = hotkeys.map((hotkey) => formatForDisplay(hotkey, { platform }));

  return (
    <li
      className={cn(
        "flex gap-3 rounded-lg border bg-muted/30 p-3.5",
        isWide ? "col-span-2 items-center" : "flex-col",
      )}
    >
      <span className="flex shrink-0 gap-1.5">
        {keyLabels.map((keyLabel) => (
          <kbd
            className="inline-flex h-10 min-w-10 items-center justify-center rounded-lg border bg-background px-2.5 font-sans text-[15px] font-semibold text-foreground shadow-[inset_0_-3px_0_rgb(0_0_0/0.08)] dark:bg-muted dark:shadow-[inset_0_-3px_0_rgb(0_0_0/0.45)]"
            key={keyLabel}
          >
            {keyLabel}
          </kbd>
        ))}
      </span>

      <span className="flex flex-col gap-0.5">
        <span className="text-sm font-medium">{t(titleKey)}</span>

        {descriptionKey !== undefined && (
          <span className="text-sm text-muted-foreground">{t(descriptionKey)}</span>
        )}
      </span>
    </li>
  );
}

export default FriendsKeyboardShortcutsDialog;
