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
}: TrustedDeviceActivityPaginationProps): JSX.Element {
  const pages = computePaginationRange(pagination.current_page, pagination.last_page);

  return (
    <Pagination>
      <PaginationContent>
        <PaginationItem>
          <ActivityPaginationPreviousButton onPageChange={onPageChange} pagination={pagination} />
        </PaginationItem>

        {pages.map((page, index) =>
          page === "ellipsis" ? (
            <PaginationItem key={`ellipsis-${index}`}>
              <PaginationEllipsis />
            </PaginationItem>
          ) : (
            <PaginationItem key={page}>
              <ActivityPaginationNumberButton
                onPageChange={onPageChange}
                page={page}
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
      size="icon"
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
      size="icon"
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
      size="icon"
      variant="outline"
    >
      <ChevronRight className="size-4" />
    </Button>
  );
}
