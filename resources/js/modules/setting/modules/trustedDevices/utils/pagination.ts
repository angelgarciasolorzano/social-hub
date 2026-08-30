import type { TrustedDevicePagination } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

const VISIBLE_PAGES = 5;

/**
 * Build the list of page numbers and ellipsis markers to render in the
 * pagination control. Always centers on `currentPage` and shows up to
 * `VISIBLE_PAGES` entries; the first and last page are never rendered
 * directly when `lastPage > VISIBLE_PAGES` — they are replaced by ellipsis.
 *
 * @param currentPage  The page the user is currently on (1-indexed).
 * @param lastPage     The total number of pages.
 * @returns            Array of page numbers or `"ellipsis"` markers.
 */
export function computePaginationRange(
  currentPage: number,
  lastPage: number,
): (number | "ellipsis")[] {
  const half = Math.floor(VISIBLE_PAGES / 2);

  if (lastPage <= VISIBLE_PAGES) {
    return Array.from({ length: lastPage }, (_, index) => index + 1);
  }

  const start = Math.max(2, currentPage - half);
  const end = Math.min(lastPage - 1, currentPage + half);

  const pages: (number | "ellipsis")[] = [];

  if (start > 2) {
    pages.push("ellipsis");
  }

  for (let page = start; page <= end; page += 1) {
    pages.push(page);
  }

  if (end < lastPage - 1) {
    pages.push("ellipsis");
  }

  return pages;
}

/**
 * Resolve the URL for a given page from the Laravel pagination links array.
 *
 * @param pagination  The full pagination payload (must contain `links`).
 * @param page        The page number to look up.
 * @returns           The URL string for that page, or `null` if disabled.
 */
export function buildPageUrl(pagination: TrustedDevicePagination, page: number): string | null {
  const link = pagination.links.find((candidate) => candidate.page === page);

  return link?.url ?? null;
}
