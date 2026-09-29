import type { JSX } from "react";

import { ChevronLeft, ChevronRight } from "lucide-react";

import type { TrustedDeviceActivityPagination as TrustedDeviceActivityPaginationData } from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
} from "@/shared/components/shadcn/ui/pagination";

const ACTIVITY_PAGINATION_VISIBLE_PAGE_COUNT = 4;

export interface TrustedDeviceActivityPaginationProps {
  pagination: TrustedDeviceActivityPaginationData;
  onPageChange: (page: number) => void;
}

export default function TrustedDeviceActivityPagination({
  pagination,
  onPageChange,
}: TrustedDeviceActivityPaginationProps): JSX.Element {
  const pages = buildActivityPaginationRange(pagination.current_page, pagination.last_page);

  return (
    <Pagination className="mx-0 w-auto shrink-0">
      <PaginationContent>
        <PaginationItem>
          <ActivityPaginationPreviousButton onPageChange={onPageChange} pagination={pagination} />
        </PaginationItem>

        {pages.map((pageNumber, pageIndex) =>
          pageNumber === "ellipsis" ? (
            <PaginationItem key={`ellipsis-${pageIndex}`}>
              <PaginationEllipsis className="size-8" />
            </PaginationItem>
          ) : (
            <PaginationItem key={pageNumber}>
              <ActivityPaginationNumberButton
                onPageChange={onPageChange}
                page={pageNumber}
                pagination={pagination}
              />
            </PaginationItem>
          ),
        )}

        <PaginationItem>
          <ActivityPaginationNextButton onPageChange={onPageChange} pagination={pagination} />
        </PaginationItem>
      </PaginationContent>
    </Pagination>
  );
}

type ActivityPaginationNumberButtonProps = Pick<
  TrustedDeviceActivityPaginationProps,
  "onPageChange" | "pagination"
> & {
  page: number;
};

function ActivityPaginationNumberButton({
  page,
  pagination,
  onPageChange,
}: ActivityPaginationNumberButtonProps): JSX.Element {
  const isActive = page === pagination.current_page;

  return (
    <Button
      aria-current={isActive ? "page" : undefined}
      onClick={() => {
        onPageChange(page);
      }}
      size="icon-sm"
      variant={isActive ? "outline" : "ghost"}
    >
      {page}
    </Button>
  );
}

type ActivityPaginationPreviousButtonProps = Pick<
  ActivityPaginationNumberButtonProps,
  "pagination" | "onPageChange"
>;

function ActivityPaginationPreviousButton({
  pagination,
  onPageChange,
}: ActivityPaginationPreviousButtonProps): JSX.Element {
  const isDisabled = pagination.current_page <= 1;

  return (
    <Button
      aria-label="Pagina anterior"
      disabled={isDisabled}
      onClick={() => {
        onPageChange(pagination.current_page - 1);
      }}
      size="icon-sm"
      variant="outline"
    >
      <ChevronLeft className="size-4" />
    </Button>
  );
}

type ActivityPaginationNextButtonProps = ActivityPaginationPreviousButtonProps;

function ActivityPaginationNextButton({
  pagination,
  onPageChange,
}: ActivityPaginationNextButtonProps): JSX.Element {
  const isDisabled = pagination.current_page >= pagination.last_page;

  return (
    <Button
      aria-label="Pagina siguiente"
      disabled={isDisabled}
      onClick={() => {
        onPageChange(pagination.current_page + 1);
      }}
      size="icon-sm"
      variant="outline"
    >
      <ChevronRight className="size-4" />
    </Button>
  );
}

function buildActivityPaginationRange(
  currentPage: number,
  lastPage: number,
): (number | "ellipsis")[] {
  if (lastPage <= ACTIVITY_PAGINATION_VISIBLE_PAGE_COUNT) {
    return Array.from({ length: lastPage }, (_pageNumber, pageIndex) => pageIndex + 1);
  }

  const nearbyStart = Math.min(Math.max(currentPage - 1, 2), lastPage - 2);
  const nearbyEnd = nearbyStart + 1;
  const pages: (number | "ellipsis")[] = [1];

  if (nearbyStart > 2) {
    pages.push("ellipsis");
  }

  pages.push(nearbyStart, nearbyEnd);

  if (nearbyEnd < lastPage - 1) {
    pages.push("ellipsis");
  }

  pages.push(lastPage);

  return pages;
}
