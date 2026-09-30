import { useHotkey, useHotkeySequences } from "@tanstack/react-hotkeys";

import { trustedDeviceGlobalShortcuts } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";

export function createTrustedDeviceShortcutHandler(action: () => void): () => void {
  return (): void => {
    if (
      typeof document !== "undefined" &&
      document.querySelector('[role="dialog"], [role="menu"]') !== null
    ) {
      return;
    }

    action();
  };
}

export interface UseTrustedDeviceShortcutsOptions {
  enabled: boolean;
  isDialogOpen: boolean;
  canOpenSummary: boolean;
  onOpenActivity: () => void;
  onOpenAddDevice: () => void;
  onOpenHelp: () => void;
  onOpenRecommendations: () => void;
  onOpenSummary: () => void;
  onRevokeAllDevices: () => void;
}

export function useTrustedDeviceShortcuts({
  enabled,
  isDialogOpen,
  canOpenSummary,
  onOpenActivity,
  onOpenAddDevice,
  onOpenHelp,
  onOpenRecommendations,
  onOpenSummary,
  onRevokeAllDevices,
}: UseTrustedDeviceShortcutsOptions): void {
  const areShortcutsEnabled = enabled && !isDialogOpen;

  const shortcutOptions = {
    enabled: areShortcutsEnabled,
    ignoreInputs: true,
    preventDefault: true,
    stopPropagation: false,
  };

  useHotkey(
    { key: "?", shift: true },
    createTrustedDeviceShortcutHandler(onOpenHelp),
    shortcutOptions,
  );

  useHotkeySequences(
    [
      {
        sequence: [...trustedDeviceGlobalShortcuts.addDevice.sequence],
        callback: createTrustedDeviceShortcutHandler(onOpenAddDevice),
      },
      {
        sequence: [...trustedDeviceGlobalShortcuts.activity.sequence],
        callback: createTrustedDeviceShortcutHandler(() => {
          onOpenActivity();
        }),
      },
      {
        sequence: [...trustedDeviceGlobalShortcuts.recommendations.sequence],
        callback: createTrustedDeviceShortcutHandler(onOpenRecommendations),
      },
      {
        sequence: [...trustedDeviceGlobalShortcuts.summary.sequence],
        callback: createTrustedDeviceShortcutHandler(onOpenSummary),
        options: { enabled: areShortcutsEnabled && canOpenSummary },
      },
      {
        sequence: [...trustedDeviceGlobalShortcuts.revokeAll.sequence],
        callback: createTrustedDeviceShortcutHandler(() => {
          onRevokeAllDevices();
        }),
      },
    ],
    shortcutOptions,
  );
}
