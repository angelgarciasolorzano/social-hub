import { createInertiaApp } from "@inertiajs/react";

import { configureEcho } from "@laravel/echo-react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

import "../css/app.css";
import { Toaster } from "./components/ui/sonner";
import { initializeTheme } from "./hooks/useAppearance";

configureEcho({
  broadcaster: "reverb",
  enabledTransports: ["ws", "wss"],
  forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? "https") === "https",
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: import.meta.env.VITE_REVERB_PORT,
  wssPort: import.meta.env.VITE_REVERB_PORT,
});

const appName = import.meta.env.VITE_APP_NAME || "Laravel";

createInertiaApp({
  progress: {
    color: "#4B5563",
  },
  resolve: (name) =>
    resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob("./pages/**/*.tsx")),
  setup({ App, el, props }) {
    const root = createRoot(el);

    root.render(
      <>
        <App {...props} />
        <Toaster position="top-center" />
      </>,
    );
  },
  title: (title) => (title ? `${title} - ${appName}` : appName),
});

// This will set light / dark mode on load...
initializeTheme();
