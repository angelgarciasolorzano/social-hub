import { useHotkey, useHotkeySequence } from "@tanstack/react-hotkeys";

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

  useHotkey({ key: "?", shift: true }, onOpenHelp, shortcutOptions);
  useHotkeySequence(["G", "N"], onOpenAddDevice, shortcutOptions);
  useHotkeySequence(["G", "A"], onOpenActivity, shortcutOptions);
}
