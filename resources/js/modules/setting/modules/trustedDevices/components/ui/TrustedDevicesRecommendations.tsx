import type { JSX } from "react";

import { ChevronRight } from "lucide-react";

import TrustedDeviceRecommendationsDialog from "@/modules/setting/modules/trustedDevices/components/dialog/TrustedDeviceRecommendationsDialog";
import { trustedDeviceRecommendationsPreview } from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";
import {
  createDialogCloseHandler,
  type DialogClosingState,
} from "@/modules/setting/shared/utils/dialog";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";

import { useDialog } from "@/shared/hooks";

import { cn } from "@/shared/lib";
import { iconColorVariants } from "@/shared/lib/styling";

interface RecommendationsDialogState extends DialogClosingState {
  kind: "open";
}

function TrustedDevicesRecommendations(): JSX.Element {
  const dialog = useDialog<RecommendationsDialogState | null>(null);
  const handleClose = createDialogCloseHandler(dialog);

  return (
    <Card>
      <CardHeader>
        <CardTitle>Recomendaciones</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {trustedDeviceRecommendationsPreview.map((recommendation) => {
          const Icon = recommendation.icon;

          return (
            <div className="flex items-center justify-between gap-4" key={recommendation.title}>
              <div className="flex items-start gap-4">
                <div
                  className={cn(
                    "flex h-10 w-10 rounded-full p-2",
                    iconColorVariants[recommendation.iconColor].iconBgClass,
                  )}
                >
                  <Icon
                    className={cn(
                      "h-6 w-6",
                      iconColorVariants[recommendation.iconColor].iconFgClass,
                    )}
                  />
                </div>

                <div className="space-y-1.5">
                  <h4 className="text-sm font-semibold">{recommendation.title}</h4>

                  <p className="line-clamp-2 text-sm text-muted-foreground">
                    {recommendation.description}
                  </p>
                </div>
              </div>
            </div>
          );
        })}
      </CardContent>

      <CardFooter className="mx-auto">
        <Button
          variant="link"
          className="text-blue-700 dark:text-blue-500"
          onClick={() => {
            dialog.show({ kind: "open", closing: false });
          }}
        >
          Mas recomendaciones
          <ChevronRight className="h-4 w-4" />
        </Button>
      </CardFooter>

      {dialog.state !== null && (
        <TrustedDeviceRecommendationsDialog open={!dialog.state.closing} onClose={handleClose} />
      )}
    </Card>
  );
}

export default TrustedDevicesRecommendations;
