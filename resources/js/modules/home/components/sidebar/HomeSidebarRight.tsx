import type { JSX } from "react";

import { useTranslation } from "@/shared/hooks";

function HomeSidebarRight(): JSX.Element {
  const { t } = useTranslation();

  return (
    <aside
      aria-label={t("home/sidebar.reserved.title")}
      className="hidden w-64 shrink-0 flex-col gap-3 overflow-y-auto border-l px-3 py-4 lg:flex"
    >
      <p className="px-3 text-xs font-medium text-muted-foreground">
        {t("home/sidebar.reserved.title")}
      </p>

      <div className="flex h-40 items-center justify-center rounded-xl border border-dashed px-4 text-center text-sm text-muted-foreground">
        {t("home/sidebar.reserved.description")}
      </div>

      <div aria-hidden="true" className="h-28 rounded-xl border border-dashed" />
    </aside>
  );
}

export default HomeSidebarRight;
