import type { JSX } from "react";
import { Fragment, useRef, useSyncExternalStore } from "react";

import { detectPlatform, formatForDisplay } from "@tanstack/react-hotkeys";
import { Keyboard } from "lucide-react";

import {
  trustedDeviceGlobalShortcuts,
  trustedDeviceRowActions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";

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
  const returnFocusRef = useRef<HTMLElement | null>(null);

  const platform = useSyncExternalStore(
    subscribeToPlatformChanges,
    detectPlatform,
    getServerPlatformSnapshot,
  );

  const generalKeyboardShortcuts = [
    {
      accessibleKeys: "Mayús y signo de interrogación",
      description: "Abrir esta ayuda",
      keys: [formatForDisplay({ key: "?", shift: true }, { platform })],
    },
  ];

  const { revokeAll: revokeAllShortcut, ...navigationShortcuts } = trustedDeviceGlobalShortcuts;

  const navigationKeyboardShortcuts = Object.values(navigationShortcuts).map((shortcut) => ({
    accessibleKeys: shortcut.sequence
      .map((key) => formatForDisplay(key, { platform }))
      .join(", luego "),
    description: shortcut.description,
    keys: shortcut.sequence.map((key) => formatForDisplay(key, { platform })),
  }));

  const revokeAllKeyboardShortcut = {
    accessibleKeys: revokeAllShortcut.sequence
      .map((key) => formatForDisplay(key, { platform }))
      .join(", luego "),
    description: revokeAllShortcut.description,
    keys: revokeAllShortcut.sequence.map((key) => formatForDisplay(key, { platform })),
  };

  const rowKeyboardShortcuts = trustedDeviceRowActions.flatMap((group) =>
    group.actions.map((action) => ({
      accessibleKeys: action.shortcut
        .map((key) => formatForDisplay(key, { platform }))
        .join(", luego "),
      description: `${action.label} para la fila seleccionada`,
      keys: action.shortcut.map((key) => formatForDisplay(key, { platform })),
    })),
  );

  const keyboardShortcutGroups = [
    { label: "GENERAL", shortcuts: generalKeyboardShortcuts },
    { label: "NAVEGACIÓN", shortcuts: navigationKeyboardShortcuts },
    {
      label: "ACCIONES",
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
      <DialogContent
        className="sm:max-w-2xl"
        onOpenAutoFocus={() => {
          const activeElement = document.activeElement;

          returnFocusRef.current =
            activeElement instanceof HTMLElement && activeElement !== document.body
              ? activeElement
              : null;
        }}
        onCloseAutoFocus={(event) => {
          const focusTarget = returnFocusRef.current;

          if (focusTarget?.isConnected) {
            event.preventDefault();
            focusTarget.focus();
          }

          returnFocusRef.current = null;
        }}
        showCloseButton={false}
      >
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2 text-lg font-semibold">
              <Keyboard aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Atajos de teclado
            </h2>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            Los atajos globales empiezan con G. Para las acciones por dispositivo, selecciona una
            fila haciendo clic en ella o enfocando su botón de acciones (⋯); luego usa los atajos
            que empiezan con F.
          </DialogDescription>
        </DialogHeader>

        <Separator />

        <ScrollArea className="max-h-150 min-h-120 px-4">
          <div className="space-y-5">
            {keyboardShortcutGroups.map((group) => (
              <section aria-labelledby={`keyboard-shortcuts-${group.label}`} key={group.label}>
                <h3
                  className="mb-2 text-xs font-medium tracking-wide text-muted-foreground"
                  id={`keyboard-shortcuts-${group.label}`}
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
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
