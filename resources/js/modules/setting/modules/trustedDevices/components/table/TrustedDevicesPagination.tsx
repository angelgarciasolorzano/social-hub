import type { JSX } from "react";

import { Link } from "@inertiajs/react";

import { ChevronLeft, ChevronRight } from "lucide-react";

import type { TrustedDevicePagination } from "@/modules/setting/modules/trustedDevices/types/trustedDevice";

import { buttonVariants } from "@/shared/components/shadcn/ui/button";
import { Label } from "@/shared/components/shadcn/ui/label";
import {
  Pagination,
  PaginationContent,
  PaginationEllipsis,
  PaginationItem,
} from "@/shared/components/shadcn/ui/pagination";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/shared/components/shadcn/ui/select";

import { cn } from "@/shared/lib";

const PAGE_SIZE_ITEMS = [
  { label: "5", value: "5" },
  { label: "10", value: "10" },
  { label: "15", value: "15" },
  { label: "25", value: "25" },
  { label: "50", value: "50" },
] as const;

interface TrustedDevicesPaginationProps {
  pagination: TrustedDevicePagination;
}

function TrustedDevicesPagination({ pagination }: TrustedDevicesPaginationProps): JSX.Element {
  const pages = computePaginationRange(pagination.current_page, pagination.last_page);

  return (
    <div className="flex items-center justify-between gap-4">
      <span className="text-sm text-muted-foreground">
        Pagina {pagination.current_page} de {pagination.last_page}
      </span>

      <Pagination className="flex-1">
        <PaginationContent>
          <PaginationItem>
            <PaginationPreviousLink pagination={pagination} />
          </PaginationItem>

          {pages.map((page, index) =>
            page === "ellipsis" ? (
              <PaginationItem key={`ellipsis-${index}`}>
                <PaginationEllipsis />
              </PaginationItem>
            ) : (
              <PaginationItem key={page}>
                <PaginationNumberLink page={page} pagination={pagination} />
              </PaginationItem>
            ),
          )}

          <PaginationItem>
            <PaginationNextLink pagination={pagination} />
          </PaginationItem>
        </PaginationContent>
      </Pagination>

      <div className="flex items-center gap-3">
        <Label className="hidden whitespace-nowrap sm:block">Filas por pagina</Label>

        <Select defaultValue={String(pagination.per_page)}>
          <SelectTrigger className="w-full max-w-32">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectGroup>
              {PAGE_SIZE_ITEMS.map((item) => (
                <SelectItem key={item.value} value={item.value}>
                  {item.label}
                </SelectItem>
              ))}
            </SelectGroup>
          </SelectContent>
        </Select>
      </div>
    </div>
  );
}

function computePaginationRange(currentPage: number, lastPage: number): (number | "ellipsis")[] {
  const visiblePages = 5;
  const half = Math.floor(visiblePages / 2);

  if (lastPage <= visiblePages) {
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

interface PaginationNumberLinkProps {
  page: number;
  pagination: TrustedDevicePagination;
}

function PaginationNumberLink({ page, pagination }: PaginationNumberLinkProps): JSX.Element {
  const isActive = page === pagination.current_page;
  const url = buildPageUrl(pagination, page);

  if (url === null) {
    return (
      <span
        aria-current={isActive ? "page" : undefined}
        className={cn(buttonVariants({ variant: isActive ? "outline" : "ghost", size: "icon" }))}
      >
        {page}
      </span>
    );
  }

  return (
    <Link
      aria-current={isActive ? "page" : undefined}
      className={cn(buttonVariants({ variant: isActive ? "outline" : "ghost", size: "icon" }))}
      href={url}
      preserveScroll
    >
      {page}
    </Link>
  );
}

interface PaginationPrevNextLinkProps {
  pagination: TrustedDevicePagination;
}

function PaginationPreviousLink({ pagination }: PaginationPrevNextLinkProps): JSX.Element {
  const url = pagination.prev_page_url;

  if (url === null) {
    return (
      <span
        aria-disabled
        aria-label="Pagina anterior"
        className={cn(
          buttonVariants({ variant: "outline", size: "icon" }),
          "pointer-events-none opacity-50",
        )}
      >
        <ChevronLeft className="size-4" />
      </span>
    );
  }

  return (
    <Link
      aria-label="Pagina anterior"
      className={cn(buttonVariants({ variant: "outline", size: "icon" }))}
      href={url}
      preserveScroll
    >
      <ChevronLeft className="size-4" />
    </Link>
  );
}

function PaginationNextLink({ pagination }: PaginationPrevNextLinkProps): JSX.Element {
  const url = pagination.next_page_url;

  if (url === null) {
    return (
      <span
        aria-disabled
        aria-label="Pagina siguiente"
        className={cn(
          buttonVariants({ variant: "outline", size: "icon" }),
          "pointer-events-none opacity-50",
        )}
      >
        <ChevronRight className="size-4" />
      </span>
    );
  }

  return (
    <Link
      aria-label="Pagina siguiente"
      className={cn(buttonVariants({ variant: "outline", size: "icon" }))}
      href={url}
      preserveScroll
    >
      <ChevronRight className="size-4" />
    </Link>
  );
}

function buildPageUrl(pagination: TrustedDevicePagination, page: number): string | null {
  const link = pagination.links.find((candidate) => candidate.page === page);

  return link?.url ?? null;
}

export default TrustedDevicesPagination;
