import { registerBlatUI } from "../blatui-core.js";

type LivewireWindow = typeof window & {
  Alpine: Parameters<typeof registerBlatUI>[0];
};

const livewireWindow = window as LivewireWindow;

document.addEventListener("alpine:init", () => {
  registerBlatUI(livewireWindow.Alpine);
});
