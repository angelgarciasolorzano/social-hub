import type { TranslationReplacements } from "@/shared/lib/translation";

import type { Locale } from "@/shared/types";

interface DownloadRecoveryCodesOptions {
  accountEmail?: string;
  filename?: string;
  locale: Locale;
  t: (key: string, replacements?: TranslationReplacements) => string;
}

/**
 * Trigger a browser download for the given recovery codes.
 *
 * Uses a Blob + ephemeral anchor (no external dependency). The file is
 * downloaded as `recovery-codes.txt` so users can store the codes offline.
 *
 * Optional metadata (account email) is included in the file header so the
 * user can identify which account the codes belong to once saved externally.
 * The header is written in the active language.
 *
 * @param codes The recovery codes, one per line of the file.
 * @param options The account email, the file name and the language to write the header in.
 */
export function downloadRecoveryCodes(
  codes: string[],
  { accountEmail, filename = "recovery-codes.txt", locale, t }: DownloadRecoveryCodesOptions,
): void {
  if (!codes.length) {
    return;
  }

  const headerLines = [t("setting/two-factor.download.title")];

  if (accountEmail !== undefined) {
    headerLines.push(t("setting/two-factor.download.account", { email: accountEmail }));
  }

  headerLines.push(
    t("setting/two-factor.download.generated", { date: new Date().toLocaleString(locale) }),
    t("setting/two-factor.download.notice"),
    "",
  );

  const body = [...headerLines, ...codes, ""].join("\n");

  const blob = new Blob([body], { type: "text/plain;charset=utf-8" });
  const url = URL.createObjectURL(blob);

  const anchor = document.createElement("a");
  anchor.href = url;
  anchor.download = filename;
  document.body.appendChild(anchor);
  anchor.click();
  document.body.removeChild(anchor);

  URL.revokeObjectURL(url);
}
