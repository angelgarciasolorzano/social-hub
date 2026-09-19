import { type JSX, useEffect } from "react";

import { router } from "@inertiajs/react";

import { toast } from "sonner";

import { Toaster } from "./shadcn/ui/sonner";

const toastTypes = ["success", "error", "warning", "info"] as const;

type ToastType = (typeof toastTypes)[number];
type FlashData = Record<string, unknown>;

function isRecord(value: unknown): value is FlashData {
  return typeof value === "object" && value !== null;
}

function isToastType(value: unknown): value is ToastType {
  return typeof value === "string" && toastTypes.includes(value as ToastType);
}

function showToast(type: ToastType, message: string): void {
  switch (type) {
    case "success":
      toast.success(message);
      break;
    case "error":
      toast.error(message);
      break;
    case "warning":
      toast.warning(message);
      break;
    case "info":
      toast.info(message);
      break;
  }
}

function showFlashToast(flashData: FlashData): void {
  const notification = flashData["notification"];

  if (isRecord(notification) && typeof notification["message"] === "string") {
    const notificationType = isToastType(notification["type"]) ? notification["type"] : "info";

    showToast(notificationType, notification["message"]);

    return;
  }

  if (isToastType(flashData["type"]) && typeof flashData["message"] === "string") {
    showToast(flashData["type"], flashData["message"]);

    return;
  }

  for (const toastType of toastTypes) {
    const message = flashData[toastType];

    if (typeof message === "string") {
      showToast(toastType, message);

      return;
    }
  }
}

export default function FlashToaster(): JSX.Element {
  useEffect(() => {
    return router.on("flash", (event) => {
      showFlashToast(event.detail.flash);
    });
  }, []);

  return <Toaster position="top-right" />;
}
