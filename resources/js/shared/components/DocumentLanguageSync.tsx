import { useEffect } from "react";

import { router } from "@inertiajs/react";

import type { SharedData } from "@/shared/types";

interface DocumentLanguageSyncProps {
  initialLocale: SharedData["locale"];
}

function DocumentLanguageSync(props: DocumentLanguageSyncProps): null {
  const { initialLocale } = props;

  useEffect(() => {
    document.documentElement.lang = initialLocale;

    return router.on("navigate", (event) => {
      document.documentElement.lang = (event.detail.page.props as unknown as SharedData).locale;
    });
  }, [initialLocale]);

  return null;
}

export default DocumentLanguageSync;
