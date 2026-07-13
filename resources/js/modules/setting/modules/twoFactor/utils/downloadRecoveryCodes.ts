/**
 * Trigger a browser download for the given recovery codes.
 *
 * Uses a Blob + ephemeral anchor (no external dependency). The file is
 * downloaded as `recovery-codes.txt` so users can store the codes offline.
 */
export function downloadRecoveryCodes(codes: string[], filename = "recovery-codes.txt"): void {
  if (!codes.length) {
    return;
  }

  const body = [
    "Códigos de respaldo para autenticación de dos factores",
    "Guárdalos en un lugar seguro. Cada código solo puede usarse una vez.",
    "",
    ...codes,
    "",
  ].join("\n");

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
