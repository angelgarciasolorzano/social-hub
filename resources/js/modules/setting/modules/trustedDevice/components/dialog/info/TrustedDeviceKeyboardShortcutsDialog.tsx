import type { JSX } from "react";
import { Fragment, useSyncExternalStore } from "react";

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
  const platform = useSyncExternalStore(
    subscribeToPlatformChanges,
    detectPlatform,
    getServerPlatformSnapshot,
  );

  const globalKeyboardShortcuts = [
    {
      accessibleKeys: "Mayús y signo de interrogación",
      description: "Abrir esta ayuda",
      keys: [formatForDisplay({ key: "?", shift: true }, { platform })],
    },
    ...Object.values(trustedDeviceGlobalShortcuts).map((shortcut) => ({
      accessibleKeys: shortcut.sequence
        .map((key) => formatForDisplay(key, { platform }))
        .join(", luego "),
      description: shortcut.description,
      keys: shortcut.sequence.map((key) => formatForDisplay(key, { platform })),
    })),
  ];

  const rowKeyboardShortcuts = trustedDeviceRowActions.flatMap((group) =>
    group.actions.map((action) => ({
      accessibleKeys: action.shortcut
        .map((key) => formatForDisplay(key, { platform }))
        .join(", luego "),
      description: `${action.label} para la fila seleccionada`,
      keys: action.shortcut.map((key) => formatForDisplay(key, { platform })),
    })),
  );

  const keyboardShortcuts = [...globalKeyboardShortcuts, ...rowKeyboardShortcuts];

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-lg" showCloseButton={false}>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2">
              <Keyboard aria-hidden="true" className="h-5 w-5 text-muted-foreground" />
              Atajos de teclado
            </h2>
          </DialogTitle>
          <DialogDescription>
            Los atajos globales empiezan con G. Para las acciones por dispositivo, selecciona una
            fila haciendo clic en ella o enfocando su botón de acciones (⋯); luego usa los atajos
            que empiezan con F.
          </DialogDescription>
        </DialogHeader>

        <ul className="space-y-3">
          {keyboardShortcuts.map((shortcut) => (
            <li
              className="flex flex-wrap items-center justify-between gap-x-4 gap-y-2 rounded-md border p-3"
              key={shortcut.description}
            >
              <span className="text-sm font-medium">{shortcut.description}</span>

              <KbdGroup aria-label={shortcut.accessibleKeys} role="group">
                {shortcut.keys.map((keyLabel, keyIndex) => (
                  <Fragment key={`${keyLabel}-${keyIndex}`}>
                    {keyIndex > 0 && (
                      <span aria-hidden="true" className="text-muted-foreground">
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

        <DialogFooter>
          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
