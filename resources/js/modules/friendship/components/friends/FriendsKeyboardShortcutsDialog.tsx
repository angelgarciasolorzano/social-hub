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
import { Kbd } from "@/shared/components/shadcn/ui/kbd";

import { useTranslation } from "@/shared/hooks";

interface FriendsShortcut {
  descriptionKey: string;
  hotkey: RegisterableHotkey;
}

const FRIENDS_SHORTCUTS: FriendsShortcut[] = [
  { descriptionKey: "friendship/friends.shortcuts.focus_filter", hotkey: "/" },
  { descriptionKey: "friendship/friends.shortcuts.leave_filter", hotkey: "Escape" },
  { descriptionKey: "friendship/friends.shortcuts.friends_tab", hotkey: "1" },
  { descriptionKey: "friendship/friends.shortcuts.received_tab", hotkey: "2" },
  { descriptionKey: "friendship/friends.shortcuts.sent_tab", hotkey: "3" },
  { descriptionKey: "friendship/friends.shortcuts.toggle_sort", hotkey: "O" },
  { descriptionKey: "friendship/friends.shortcuts.show_help", hotkey: { key: "?", shift: true } },
];

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

  const getServerPlatformSnapshot = (): ReturnType<typeof detectPlatform> => {
    return "linux";
  };

  const platform = useSyncExternalStore(
    subscribeToPlatformChanges,
    detectPlatform,
    getServerPlatformSnapshot,
  );

  return (
    <Dialog onOpenChange={onOpenChange} open={open}>
      <DialogContent className="sm:max-w-md" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <KeyboardIcon aria-hidden="true" className="size-5 text-muted-foreground" />
            {t("friendship/friends.shortcuts.title")}
          </DialogTitle>

          <DialogDescription>{t("friendship/friends.shortcuts.description")}</DialogDescription>
        </DialogHeader>

        <ul className="space-y-1">
          {FRIENDS_SHORTCUTS.map((friendsShortcut) => {
            const keyLabel = formatForDisplay(friendsShortcut.hotkey, { platform });

            return (
              <li
                className="flex min-h-9 items-center justify-between gap-4 py-1"
                key={friendsShortcut.descriptionKey}
              >
                <span className="text-sm">{t(friendsShortcut.descriptionKey)}</span>

                <Kbd aria-label={keyLabel}>{keyLabel}</Kbd>
              </li>
            );
          })}
        </ul>

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">{t("friendship/friends.shortcuts.close")}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default FriendsKeyboardShortcutsDialog;
