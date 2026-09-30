const MAX_VISIBLE_PAGE_NUMBERS = 5;

/**
 * Build the list of page numbers and ellipsis markers to render in the
 * pagination control. Always centers on `currentPage` and shows up to
 * `MAX_VISIBLE_PAGE_NUMBERS` page numbers total, including the first and last page.
 *
 * @param currentPage  The page the user is currently on (1-indexed).
 * @param lastPage     The total number of pages.
 * @returns            Array of page numbers or `"ellipsis"` markers.
 */
export function computePaginationRange(
  currentPage: number,
  lastPage: number,
): (number | "ellipsis")[] {
  const nearbyPageCount = MAX_VISIBLE_PAGE_NUMBERS - 2;
  const halfNearbyPageCount = Math.floor(nearbyPageCount / 2);

  if (lastPage <= MAX_VISIBLE_PAGE_NUMBERS) {
    return Array.from({ length: lastPage }, (_pageNumber, pageIndex) => pageIndex + 1);
  }

  const start = Math.max(
    2,
    Math.min(currentPage - halfNearbyPageCount, lastPage - nearbyPageCount),
  );
  const end = start + nearbyPageCount - 1;

  const pages: (number | "ellipsis")[] = [1];

  if (start > 2) {
    pages.push("ellipsis");
  }

  for (let page = start; page <= end; page += 1) {
    pages.push(page);
  }

  if (end < lastPage - 1) {
    pages.push("ellipsis");
  }

  pages.push(lastPage);

  return pages;
}

/**
 * Resolve the URL for a given page from the Laravel pagination links array.
 * Accepts any pagination shape that exposes a `links[]` of `{ page, url }`
 * entries — works for `TrustedDevicePagination`, `TrustedDeviceActivityPagination`,
 * or any future paginator that mirrors Laravel's standard payload.
 *
 * @param pagination  The pagination payload (must contain `links`).
 * @param page        The page number to look up.
 * @returns           The URL string for that page, or `null` if disabled.
 */
export function buildPageUrl(
  pagination: { links: { page: number; url: string | null }[] },
  page: number,
): string | null {
  const link = pagination.links.find((candidate) => candidate.page === page);

  return link?.url ?? null;
}
