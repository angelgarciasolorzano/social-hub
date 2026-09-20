import type { JSX } from "react";
import { useEffect, useRef, useState } from "react";

import { router, usePage } from "@inertiajs/react";

import { Clock, Info, Search, X } from "lucide-react";

import TrustedDeviceActivityEventList from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityEventList";
import TrustedDeviceActivityFiltersPopover from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityFiltersPopover";
import TrustedDeviceActivityPagination from "@/modules/setting/modules/trustedDevice/components/table/trustedDeviceActivityDialog/TrustedDeviceActivityPagination";
import { useTrustedDeviceActivityFilters } from "@/modules/setting/modules/trustedDevice/hooks/useTrustedDeviceActivityFilters";
import type {
  TrustedDeviceActivityFilters,
  TrustedDeviceActivityPagination as TrustedDeviceActivityPaginationData,
} from "@/modules/setting/modules/trustedDevice/types/trustedDeviceActivityDialog";

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
import { Skeleton } from "@/shared/components/shadcn/ui/skeleton";

import type { SharedData } from "@/shared/types";

interface TrustedDeviceActivityDialogProps {
  open: boolean;
  onClose: () => void;
}

interface TrustedDeviceActivityPayload {
  activityLog: TrustedDeviceActivityPaginationData;
  activityFilters: TrustedDeviceActivityFilters;
}

interface TrustedDeviceActivityDialogPageProps extends SharedData {
  activityDialog?: TrustedDeviceActivityPayload;
}

export default function TrustedDeviceActivityDialog({
  open,
  onClose,
}: TrustedDeviceActivityDialogProps): JSX.Element {
  const { activityDialog } = usePage<TrustedDeviceActivityDialogPageProps>().props;

  const hasRequestedActivityRef = useRef(false);
  const [hasFreshActivity, setHasFreshActivity] = useState(false);

  useEffect(() => {
    if (hasRequestedActivityRef.current) return;

    hasRequestedActivityRef.current = true;

    router.reload({
      only: ["activityDialog"],
      onFinish: () => {
        setHasFreshActivity(true);
      },
    });
  }, []);

  const isLoading = !hasFreshActivity || activityDialog === undefined;

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (!next) onClose();
      }}
    >
      <DialogContent className="sm:max-w-3xl">
        <DialogHeader>
          <DialogTitle>
            <div className="flex items-center gap-2">
              <Clock className="h-5 w-5 text-muted-foreground" />
              Actividad reciente de dispositivos
            </div>
          </DialogTitle>
          <DialogDescription>
            Historial de eventos relacionados con tus dispositivos de confianza.
          </DialogDescription>
        </DialogHeader>

        {isLoading ? (
          <div className="min-h-60 space-y-6">
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
            initialActivity={activityDialog.activityLog}
            initialFilters={activityDialog.activityFilters}
          />
        )}
      </DialogContent>
    </Dialog>
  );
}

interface TrustedDeviceActivityDialogBodyProps {
  initialActivity: TrustedDeviceActivityPaginationData;
  initialFilters: TrustedDeviceActivityFilters;
}

function TrustedDeviceActivityDialogBody({
  initialActivity,
  initialFilters,
}: TrustedDeviceActivityDialogBodyProps): JSX.Element {
  const { committedFilters, filters, goToPage, resetFilters, updateFilter } =
    useTrustedDeviceActivityFilters(initialFilters);

  const hasActiveFilters =
    committedFilters.search !== "" ||
    (committedFilters.action !== null && committedFilters.action.length > 0) ||
    (committedFilters.sinceDays !== null && committedFilters.sinceDays.length > 0);

  return (
    <>
      <div className="flex items-center gap-2">
        <InputGroup className="flex-1">
          <InputGroupAddon>
            <Search />
          </InputGroupAddon>

          <InputGroupInput
            onChange={(event) => {
              updateFilter("search", event.target.value);
            }}
            placeholder="Buscar por dispositivo, nombre o IP..."
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

      <TrustedDeviceActivityEventList
        events={initialActivity.data}
        hasActiveFilters={hasActiveFilters}
      />

      <DialogFooter className="flex items-center justify-between sm:justify-between">
        <div className="flex items-center gap-2">
          <Info className="h-5 w-5 text-muted-foreground" />

          <span className="text-xs text-muted-foreground">
            {initialActivity.total === 0
              ? "Sin eventos"
              : `Mostrando ${initialActivity.from ?? 0}-${initialActivity.to ?? 0} de ${initialActivity.total} eventos`}
          </span>
        </div>

        <div className="flex items-center gap-3">
          {initialActivity.last_page > 1 && (
            <TrustedDeviceActivityPagination onPageChange={goToPage} pagination={initialActivity} />
          )}

          <DialogClose asChild>
            <Button variant="outline">Cerrar</Button>
          </DialogClose>
        </div>
      </DialogFooter>
    </>
  );
}
