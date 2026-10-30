import type { JSX } from "react";
import { Fragment, useSyncExternalStore } from "react";

import { detectPlatform, formatForDisplay } from "@tanstack/react-hotkeys";
import { Keyboard } from "lucide-react";

import {
  trustedDeviceGlobalShortcuts,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import { useDialogFocusRestoration } from "@/modules/setting/shared/hooks/useDialogFocusRestoration";

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
import { Kbd, KbdGroup } from "@/shared/components/shadcn/ui/kbd";
import { ScrollArea } from "@/shared/components/shadcn/ui/scroll-area";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useTranslation } from "@/shared/hooks";

function subscribeToPlatformChanges(): () => void {
  return () => undefined;
}

function getServerPlatformSnapshot(): ReturnType<typeof detectPlatform> {
  return "linux";
}

interface TrustedDeviceKeyboardShortcutsDialogProps {
  onClose: () => void;
  open: boolean;
}

export default function TrustedDeviceKeyboardShortcutsDialog({
  onClose,
  open,
}: TrustedDeviceKeyboardShortcutsDialogProps): JSX.Element {
  const { t } = useTranslation();
  const dialogFocusRestoration = useDialogFocusRestoration();

  const platform = useSyncExternalStore(
    subscribeToPlatformChanges,
    detectPlatform,
    getServerPlatformSnapshot,
  );

  const generalKeyboardShortcuts = [
    {
      accessibleKeys: t("setting/trusted-device-dialogs.keyboard_shortcuts.open_help_keys"),
      description: t("setting/trusted-device-dialogs.keyboard_shortcuts.open_help"),
      keys: [formatForDisplay({ key: "?", shift: true }, { platform })],
    },
  ];

  const { revokeAll: revokeAllShortcut, ...navigationShortcuts } = trustedDeviceGlobalShortcuts;

  const navigationKeyboardShortcuts = Object.values(navigationShortcuts).map((shortcut) => ({
    accessibleKeys: shortcut.sequence
      .map((key) => formatForDisplay(key, { platform }))
      .join(t("setting/trusted-device-dialogs.keyboard_shortcuts.then")),
    description: t(shortcut.description),
    keys: shortcut.sequence.map((key) => formatForDisplay(key, { platform })),
  }));

  const revokeAllKeyboardShortcut = {
    accessibleKeys: revokeAllShortcut.sequence
      .map((key) => formatForDisplay(key, { platform }))
      .join(t("setting/trusted-device-dialogs.keyboard_shortcuts.then")),
    description: t(revokeAllShortcut.description),
    keys: revokeAllShortcut.sequence.map((key) => formatForDisplay(key, { platform })),
  };

  const rowKeyboardShortcuts = trustedDeviceRowActions.flatMap((group) =>
    group.actions.map((action) => ({
      accessibleKeys: action.shortcut
        .map((key) => formatForDisplay(key, { platform }))
        .join(t("setting/trusted-device-dialogs.keyboard_shortcuts.then")),
      description: t("setting/trusted-device-dialogs.keyboard_shortcuts.for_selected_row", {
        action: t(action.label),
      }),
      keys: action.shortcut.map((key) => formatForDisplay(key, { platform })),
    })),
  );

  const keyboardShortcutGroups = [
    {
      id: "general",
      label: t("setting/trusted-device-dialogs.keyboard_shortcuts.groups.general"),
      shortcuts: generalKeyboardShortcuts,
    },
    {
      id: "navigation",
      label: t("setting/trusted-device-dialogs.keyboard_shortcuts.groups.navigation"),
      shortcuts: navigationKeyboardShortcuts,
    },
    {
      id: "actions",
      label: t("setting/trusted-device-dialogs.keyboard_shortcuts.groups.actions"),
      shortcuts: [revokeAllKeyboardShortcut, ...rowKeyboardShortcuts],
    },
  ];

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-2xl" showCloseButton={false} {...dialogFocusRestoration}>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <Keyboard aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              {t("setting/trusted-devices.page.keyboard_shortcuts")}
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            {t("setting/trusted-device-dialogs.keyboard_shortcuts.description")}
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <ScrollArea className="max-h-150 min-h-120 px-4">
          <div className="space-y-5">
            {keyboardShortcutGroups.map((group) => (
              <section aria-labelledby={`keyboard-shortcuts-${group.id}`} key={group.id}>
                <h3
                  className="mb-2 text-xs font-medium tracking-wide text-muted-foreground"
                  id={`keyboard-shortcuts-${group.id}`}
                >
                  {group.label}
                </h3>

                <ul className="space-y-1" role="list">
                  {group.shortcuts.map((shortcut) => (
                    <li
                      className="flex min-h-9 flex-wrap items-center justify-between gap-x-4 gap-y-2 py-1"
                      key={shortcut.description}
                    >
                      <span className="text-sm font-normal">{shortcut.description}</span>

                      <KbdGroup aria-label={shortcut.accessibleKeys} role="group">
                        {shortcut.keys.map((keyLabel, keyIndex) => (
                          <Fragment key={`${keyLabel}-${keyIndex}`}>
                            {keyIndex > 0 && (
                              <span
                                aria-hidden="true"
                                className="text-xs font-normal text-muted-foreground"
                              >
                                →
                              </span>
                            )}
                            <Kbd aria-hidden="true">{keyLabel}</Kbd>
                          </Fragment>
                        ))}
                      </KbdGroup>
                    </li>
                  ))}
                </ul>
              </section>
            ))}
          </div>
        </ScrollArea>

        <Separator />

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">{t("setting/common.actions.close")}</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
