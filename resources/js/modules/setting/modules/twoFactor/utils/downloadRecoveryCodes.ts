interface DownloadRecoveryCodesOptions {
  accountEmail?: string;
  filename?: string;
}

/**
 * Trigger a browser download for the given recovery codes.
 *
 * Uses a Blob + ephemeral anchor (no external dependency). The file is
 * downloaded as `recovery-codes.txt` so users can store the codes offline.
 *
 * Optional metadata (account email) is included in the file header so the
 * user can identify which account the codes belong to once saved externally.
 */
export function downloadRecoveryCodes(
  codes: string[],
  { accountEmail, filename = "recovery-codes.txt" }: DownloadRecoveryCodesOptions = {},
): void {
  if (!codes.length) {
    return;
  }

  const headerLines = ["Códigos de respaldo para autenticación de dos factores"];

  if (accountEmail !== undefined) {
    headerLines.push(`Cuenta: ${accountEmail}`);
  }

  headerLines.push(
    `Generados: ${new Date().toLocaleString("es-MX")}`,
    "Guárdalos en un lugar seguro. Cada código solo puede usarse una vez.",
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
