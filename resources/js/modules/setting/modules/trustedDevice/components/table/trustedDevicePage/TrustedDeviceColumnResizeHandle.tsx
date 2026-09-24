import type { JSX, MouseEventHandler, TouchEventHandler } from "react";

import type { ColumnResizeDirection } from "@tanstack/react-table";
import { GripVertical } from "lucide-react";

import { cn } from "@/shared/lib";

interface TrustedDeviceColumnResizeHandleProps {
  ariaLabel: string;
  isResizing: boolean;
  maximumSize: number;
  minimumSize: number;
  onMouseDown: MouseEventHandler<HTMLDivElement>;
  onResize: (nextSize: number) => void;
  onTouchStart: TouchEventHandler<HTMLDivElement>;
  resizeDirection: ColumnResizeDirection;
  size: number;
}

function TrustedDeviceColumnResizeHandle({
  ariaLabel,
  isResizing,
  maximumSize,
  minimumSize,
  onMouseDown,
  onResize,
  onTouchStart,
  resizeDirection,
  size,
}: TrustedDeviceColumnResizeHandleProps): JSX.Element {
  return (
    <div
      aria-label={ariaLabel}
      aria-orientation="vertical"
      aria-valuemax={maximumSize}
      aria-valuemin={minimumSize}
      aria-valuenow={size}
      aria-valuetext={`${size} píxeles`}
      className={cn(
        "absolute inset-y-0 right-0 z-10 flex w-6 cursor-col-resize touch-none items-center justify-center opacity-0 transition-opacity outline-none select-none group-hover:opacity-100 focus-visible:opacity-100 [@media(hover:none)]:opacity-100",
        isResizing && "opacity-100",
      )}
      onKeyDown={(event) => {
        if (event.key !== "ArrowLeft" && event.key !== "ArrowRight") {
          return;
        }

        event.preventDefault();

        const direction = resizeDirection === "rtl" ? -1 : 1;
        const arrowDirection = event.key === "ArrowRight" ? 1 : -1;
        const nextSize = Math.min(
          maximumSize,
          Math.max(minimumSize, size + arrowDirection * direction * 16),
        );

        onResize(nextSize);
      }}
      onMouseDown={onMouseDown}
      onTouchStart={onTouchStart}
      role="separator"
      tabIndex={0}
      title="Mantén presionado y arrastra para cambiar el ancho"
    >
      <GripVertical
        aria-hidden="true"
        className={cn("size-4", isResizing ? "text-primary" : "text-muted-foreground")}
      />
    </div>
  );
}

export default TrustedDeviceColumnResizeHandle;
