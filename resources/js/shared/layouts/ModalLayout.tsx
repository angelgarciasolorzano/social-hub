import type { JSX, ReactNode } from "react";

import { ModalRoot } from "@inertiaui/modal-react";

interface ModalLayoutProps {
  children: ReactNode;
}

/**
 * Global layout that mounts the <ModalRoot /> portaled by @inertiaui/modal-react.
 *
 * `ModalRoot` reads the ModalStackContext (provided in `app.tsx`) and renders
 * any modal currently in the stack as a React Portal. Pages opt into this
 * layout by default in `app.tsx`'s `setPageLayout(ModalLayout)`; layouts
 * that already declare their own `page.layout` (e.g. AuthCardLayout,
 * SettingLayout) are not affected.
 */
export default function ModalLayout({ children }: ModalLayoutProps): JSX.Element {
  return (
    <>
      {children}
      <ModalRoot />
    </>
  );
}
