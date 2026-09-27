import { useHotkey, useHotkeySequence } from "@tanstack/react-hotkeys";

function createShortcutHandler(action: () => void): () => void {
  return (): void => {
    if (typeof document !== "undefined" && document.querySelector('[role="dialog"]') !== null) {
      return;
    }

    action();
  };
}

export interface UseTrustedDeviceShortcutsOptions {
  enabled: boolean;
  isDialogOpen: boolean;
  onOpenActivity: () => void;
  onOpenAddDevice: () => void;
  onOpenHelp: () => void;
}

export function useTrustedDeviceShortcuts({
  enabled,
  isDialogOpen,
  onOpenActivity,
  onOpenAddDevice,
  onOpenHelp,
}: UseTrustedDeviceShortcutsOptions): void {
  const areShortcutsEnabled = enabled && !isDialogOpen;

  const shortcutOptions = {
    enabled: areShortcutsEnabled,
    ignoreInputs: true,
    preventDefault: true,
    stopPropagation: false,
  };

  useHotkey({ key: "?", shift: true }, createShortcutHandler(onOpenHelp), shortcutOptions);
  useHotkeySequence(["G", "N"], createShortcutHandler(onOpenAddDevice), shortcutOptions);
  useHotkeySequence(["G", "A"], createShortcutHandler(onOpenActivity), shortcutOptions);
}
