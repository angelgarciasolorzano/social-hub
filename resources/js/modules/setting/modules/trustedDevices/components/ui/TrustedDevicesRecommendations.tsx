import type { JSX } from "react";

import { ChevronRight } from "lucide-react";

import { trustedDeviceRecommendations } from "@/modules/setting/modules/trustedDevices/data/trustedDevicesOverview";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Card,
  CardContent,
  CardFooter,
  CardHeader,
  CardTitle,
} from "@/shared/components/shadcn/ui/card";

import { cn } from "@/shared/lib";
import { iconColorVariants } from "@/shared/lib/styling";

/**
 * Right-side "Recomendaciones" card. Moved out of TrustedDevice.tsx (SOC-22)
 * so each panel card lives next to its peers in components/ui/ and can wire
 * its own `useDialog` locally for the detail dialog (Phase 6).
 */
function TrustedDevicesRecommendations(): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <CardTitle>Recomendaciones</CardTitle>
      </CardHeader>
      <CardContent className="space-y-6">
        {trustedDeviceRecommendations.map((recommendation) => {
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

                  <p className="text-sm text-muted-foreground">{recommendation.description}</p>
                </div>
              </div>
            </div>
          );
        })}
      </CardContent>
      <CardFooter className="mx-auto">
        {/* SOC-22: CTA "Más recomendaciones" — wired to the detail dialog in Phase 6. */}
        <Button variant="link" className="text-blue-700 dark:text-blue-500">
          Mas recomendaciones
          <ChevronRight className="h-4 w-4" />
        </Button>
      </CardFooter>
    </Card>
  );
}

export default TrustedDevicesRecommendations;
