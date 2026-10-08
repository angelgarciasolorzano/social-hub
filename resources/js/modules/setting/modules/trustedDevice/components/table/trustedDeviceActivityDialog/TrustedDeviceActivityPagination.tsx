import type { JSX } from "react";

import { ChevronLeft, ChevronRight } from "lucide-react";

import type { TrustedDeviceActivityPagination as TrustedDeviceActivityPaginationData } from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";
import { computePaginationRange } from "@/modules/setting/modules/trustedDevice/utils/pagination";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
} from "@/shared/components/shadcn/ui/pagination";

export interface TrustedDeviceActivityPaginationProps {
  pagination: TrustedDeviceActivityPaginationData;
  onPageChange: (page: number) => void;
}

export default function TrustedDeviceActivityPagination({
  pagination,
  onPageChange,
}: TrustedDeviceActivityPaginationProps): JSX.Element | null {
  if (pagination.last_page <= 1) {
    return null;
  }

  const pages = computePaginationRange(pagination.current_page, pagination.last_page);

  return (
    <Pagination
      aria-label="Paginación de la actividad de dispositivos"
      className="mx-0 w-auto shrink-0"
    >
      <PaginationContent role="list">
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
      aria-label={isActive ? `Página actual ${page}` : `Ir a la página ${page}`}
      aria-current={isActive ? "page" : undefined}
      className="text-sm font-medium"
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
      aria-label="Página anterior"
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
      aria-label="Página siguiente"
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
