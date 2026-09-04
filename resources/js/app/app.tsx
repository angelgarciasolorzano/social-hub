import type { JSX, PropsWithChildren } from "react";

import { createInertiaApp } from "@inertiajs/react";

import ModalLayout from "@/shared/layouts/ModalLayout";
import { ModalStackProvider } from "@inertiaui/modal-react";
import { configureEcho } from "@laravel/echo-react";
import dayjs from "dayjs";
import "dayjs/locale/es";
import relativeTime from "dayjs/plugin/relativeTime";

import SettingLayout from "@/modules/setting/shared/layouts/SettingLayout";

import { TooltipProvider } from "@/shared/components/shadcn/ui/tooltip";

import "../../css/app.css";
import { initializeTheme } from "../shared/hooks/useAppearance";

dayjs.extend(relativeTime);
dayjs.locale("es");

const {
  VITE_APP_NAME,
  VITE_REVERB_APP_KEY,
  VITE_REVERB_HOST,
  VITE_REVERB_PORT,
  VITE_REVERB_SCHEME,
} = import.meta.env;

const reverbPort = Number(VITE_REVERB_PORT ?? "8000");
const appName = VITE_APP_NAME;

configureEcho({
  broadcaster: "reverb",
  enabledTransports: ["ws", "wss"],
  forceTLS: (VITE_REVERB_SCHEME ?? "https") === "https",
  key: VITE_REVERB_APP_KEY,
  wsHost: VITE_REVERB_HOST,
  wsPort: reverbPort,
  wssPort: reverbPort,
});

void createInertiaApp({
  pages: "../modules",
  title: (title) => (title ? `${title} - ${appName}` : appName),
  strictMode: true,
  layout: (name) => {
    // eslint-disable-next-line @typescript-eslint/switch-exhaustiveness-check
    switch (true) {
      case name.startsWith("setting/"):
        return function SettingLayoutWrapper({ children }: PropsWithChildren): JSX.Element {
          return (
            <ModalLayout>
              <SettingLayout>{children}</SettingLayout>
            </ModalLayout>
          );
        };
      default:
        return ModalLayout;
    }
  },
  withApp(app) {
    return (
      <ModalStackProvider>
        <TooltipProvider>{app}</TooltipProvider>
      </ModalStackProvider>
    );
  },
  progress: {
    color: "#4B5563",
  },
});

initializeTheme();
