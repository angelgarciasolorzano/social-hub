/**
 * Filter a submit `only:` array down to keys actually present on the page,
 * so the same dialog works from controllers with different `$props` shapes.
 *
 * @example
 *   submit(action(), { only: pickReloadKeys(usePage().props, ["stats", "trustedDevices"]) });
 */
export function pickReloadKeys<TKeys extends string>(
  pageProps: Record<string, unknown>,
  candidateKeys: readonly TKeys[],
): TKeys[] {
  return candidateKeys.filter((key) => pageProps[key] !== undefined);
}
