import { useCallback, useEffect, useState } from "react";

import { useClipboard } from "@/shared/hooks/useClipboard";

interface UseCopyWithCountdownParams {
  durationMs: number;
  tickMs: number;
}

interface UseCopyWithCountdownReturn {
  copiedText: string | null;
  copy: (text: string) => void;
  isActive: boolean;
  progressPercent: number;
  secondsLeft: number;
}

export function useCopyWithCountdown({
  durationMs,
  tickMs,
}: UseCopyWithCountdownParams): UseCopyWithCountdownReturn {
  const totalTicks = Math.floor(durationMs / tickMs);

  const [copiedText, copyToClipboard] = useClipboard({ resetTimeout: durationMs });

  const [copyStartedAt, setCopyStartedAt] = useState<number | null>(null);
  const [secondsLeft, setSecondsLeft] = useState<number>(0);

  const isActive = copyStartedAt !== null;
  const progressPercent = isActive ? (secondsLeft / totalTicks) * 100 : 0;

  useEffect(() => {
    if (copyStartedAt === null) {
      return;
    }

    const interval = setInterval(() => {
      setSecondsLeft((prev) => Math.max(0, prev - 1));
    }, tickMs);

    const timeout = setTimeout(() => {
      setCopyStartedAt(null);
    }, durationMs);

    return () => {
      clearInterval(interval);
      clearTimeout(timeout);
    };
  }, [copyStartedAt, tickMs, durationMs]);

  const copy = useCallback(
    (text: string): void => {
      setSecondsLeft(totalTicks);
      setCopyStartedAt(Date.now());
      void copyToClipboard(text);
    },
    [copyToClipboard, totalTicks],
  );

  return {
    copiedText,
    copy,
    isActive,
    progressPercent,
    secondsLeft,
  };
}
