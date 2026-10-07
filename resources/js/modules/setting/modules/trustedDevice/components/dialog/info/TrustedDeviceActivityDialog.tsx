import type { JSX } from "react";
import { useEffect, useId, useRef, useState } from "react";

import { usePage } from "@inertiajs/react";

import { Clock, Info, Search, X } from "lucide-react";

import TrustedDeviceActivityActiveFilterChips from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityActiveFilterChips";
import TrustedDeviceActivityEventList from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityEventList";
import TrustedDeviceActivityFiltersPopover from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFiltersPopover";
import TrustedDeviceActivityPagination from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityPagination";
import {
  type TrustedDevicePerPage,
  trustedDevicePerPageOptions,
} from "@/modules/setting/modules/trustedDevice/data/trustedDeviceFilters";
import type { TrustedDeviceActivityFiltersController } from "@/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceActivityFilters";
import type { TrustedDeviceActivityPagination as TrustedDeviceActivityPaginationData } from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";
import {
  InputGroup,
  InputGroupAddon,
  InputGroupButton,
  InputGroupInput,
} from "@/shared/components/shadcn/ui/input-group";
import { Label } from "@/shared/components/shadcn/ui/label";
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/shared/components/shadcn/ui/select";
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import type { SharedData } from "@/shared/types";

interface TrustedDeviceActivityDialogProps {
  filterController: TrustedDeviceActivityFiltersController;
  open: boolean;
  onClose: () => void;
}

interface TrustedDeviceActivityPayload {
  activityLog: TrustedDeviceActivityPaginationData;
}

interface TrustedDeviceActivityDialogPageProps extends SharedData {
  activityDialog?: TrustedDeviceActivityPayload;
}

export default function TrustedDeviceActivityDialog({
  open,
  onClose,
  filterController,
}: TrustedDeviceActivityDialogProps): JSX.Element {
  const { activityDialog } = usePage<TrustedDeviceActivityDialogPageProps>().props;
  const { reload } = filterController;

  const hasRequestedActivityRef = useRef(false);
  const [hasFreshActivity, setHasFreshActivity] = useState(false);

  useEffect(() => {
    if (hasRequestedActivityRef.current) return;

    hasRequestedActivityRef.current = true;

    reload(() => {
      setHasFreshActivity(true);
    });
  }, [reload]);

  const isLoading = !hasFreshActivity || activityDialog === undefined;

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="max-h-[calc(100dvh-2rem)] grid-rows-[auto_minmax(0,1fr)] sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle className="text-lg font-semibold">
            <div className="flex items-center gap-2">
              <Clock className="h-5 w-5 text-muted-foreground" />
              Actividad reciente de dispositivos
            </div>
          </DialogTitle>
          <DialogDescription className="text-sm font-normal">
            Historial de eventos relacionados con tus dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <div className="min-h-0 space-y-6 overflow-y-auto">
            <div className="flex items-center gap-2">
              <Skeleton className="h-10 flex-1" />
              <Skeleton className="h-10 w-10" />
            </div>

            <div className="space-y-4">
              {["first", "second", "third"].map((eventKey) => (
                <div className="flex items-center justify-between gap-4" key={eventKey}>
                  <div className="flex items-center gap-4">
                    <Skeleton className="h-10 w-10 rounded-full" />
                    <div className="space-y-2">
                      <Skeleton className="h-4 w-40" />
                      <Skeleton className="h-4 w-28" />
                    </div>
                  </div>

                  <Skeleton className="h-4 w-20" />
                </div>
              ))}
            </div>

            <div className="flex flex-wrap items-center justify-between gap-4 border-t pt-4">
              <div className="flex min-w-0 items-center gap-2">
                <Skeleton className="size-5 shrink-0" />
                <Skeleton className="h-4 w-32 max-w-full" />
              </div>

              <div className="flex items-center gap-3">
                <Skeleton className="h-9 w-24 max-w-full" />
                <Skeleton className="h-9 w-20 max-w-full" />
              </div>
            </div>
          </div>
        ) : (
          <TrustedDeviceActivityDialogBody
            filterController={filterController}
            initialActivity={activityDialog.activityLog}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

interface TrustedDeviceActivityDialogBodyProps {
  filterController: TrustedDeviceActivityFiltersController;
  initialActivity: TrustedDeviceActivityPaginationData;
}

function TrustedDeviceActivityDialogBody({
  filterController,
  initialActivity,
}: TrustedDeviceActivityDialogBodyProps): JSX.Element {
  const { committedFilters, filters, goToPage, resetFilters, updateFilter } = filterController;
  const searchInputId = useId();

  const hasActiveFilters =
    committedFilters.search !== "" ||
    (committedFilters.action !== null && committedFilters.action.length > 0) ||
    (committedFilters.sinceDays !== null && committedFilters.sinceDays.length > 0);

  return (
    <div className="flex min-h-0 flex-col gap-4">
      <div className="flex items-center gap-2">
        <InputGroup className="flex-1">
          <Label className="sr-only" htmlFor={searchInputId}>
            Buscar actividad de dispositivos
          </Label>
          <InputGroupAddon>
            <Search />
          </InputGroupAddon>

          <InputGroupInput
            id={searchInputId}
            onChange={(event) => {
              updateFilter("search", event.target.value);
            }}
            placeholder="Buscar por dispositivo, nombre o IP..."
            autoFocus
            value={filters.search}
          />

          {filters.search !== "" && (
            <InputGroupAddon align="inline-end">
              <InputGroupButton
                aria-label="Limpiar búsqueda"
                onClick={() => {
                  updateFilter("search", "");
                }}
                size="icon-xs"
              >
                <X />
              </InputGroupButton>
            </InputGroupAddon>
          )}
        </InputGroup>

        <TrustedDeviceActivityFiltersPopover
          actionFilter={filters.action}
          onActionFilterChange={(value) => {
            updateFilter("action", value);
          }}
          onResetFilters={resetFilters}
          onSinceDaysFilterChange={(value) => {
            updateFilter("sinceDays", value);
          }}
          sinceDaysFilter={filters.sinceDays}
        />
      </div>

      <TrustedDeviceActivityActiveFilterChips
        filters={committedFilters}
        onActionFilterChange={(value) => {
          updateFilter("action", value);
        }}
        onResetFilters={resetFilters}
        onSearchChange={(value) => {
          updateFilter("search", value);
        }}
        onSinceDaysFilterChange={(value) => {
          updateFilter("sinceDays", value);
        }}
      />

      <TrustedDeviceActivityEventList
        events={initialActivity.data}
        hasActiveFilters={hasActiveFilters}
      />

      <DialogFooter className="flex shrink-0 flex-col gap-4 sm:flex-col">
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex items-center justify-between gap-2 sm:justify-start">
            <Label
              className="text-xs whitespace-nowrap text-muted-foreground"
              htmlFor="trusted-device-activity-per-page"
            >
              Eventos por página
            </Label>

            <Select
              value={String(filters.perPage)}
              onValueChange={(value) => {
                updateFilter("perPage", Number.parseInt(value, 10) as TrustedDevicePerPage);
              }}
            >
              <SelectTrigger className="h-9 w-20" id="trusted-device-activity-per-page">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectGroup>
                  {trustedDevicePerPageOptions.map((option) => (
                    <SelectItem key={option} value={String(option)}>
                      {String(option)}
                    </SelectItem>
                  ))}
                </SelectGroup>
              </SelectContent>
            </Select>
          </div>

          {initialActivity.last_page > 1 && (
            <div className="flex justify-end">
              <TrustedDeviceActivityPagination
                onPageChange={goToPage}
                pagination={initialActivity}
              />
            </div>
          )}
        </div>

        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="flex min-w-0 items-center gap-2">
            <Info className="h-5 w-5 shrink-0 text-muted-foreground" />

            <span className="text-xs font-normal text-muted-foreground">
              {initialActivity.total === 0
                ? "Sin eventos"
                : `Mostrando ${initialActivity.from ?? 0}-${initialActivity.to ?? 0} de ${initialActivity.total} eventos`}
            </span>
          </div>

          <div className="flex justify-end">
            <DialogClose asChild>
              <Button variant="outline">Cerrar</Button>
            </DialogClose>
          </div>
        </div>
      </DialogFooter>
    </div>
  );
}
