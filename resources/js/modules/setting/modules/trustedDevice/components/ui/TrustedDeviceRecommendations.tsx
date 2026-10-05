import type { JSX } from "react";

import { ChevronRight, ShieldQuestionMark } from "lucide-react";

import { trustedDeviceRecommendationsPreview } from "@/modules/setting/modules/trustedDevice/data/trustedDeviceOverview";
import EmptyState from "@/modules/setting/shared/components/EmptyState";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardFooter, CardHeader } from "@/shared/components/shadcn/ui/card";

import { cn } from "@/shared/lib";
import { iconColorVariants } from "@/shared/lib/styling";

interface TrustedDeviceRecommendationsProps {
  onOpenRecommendations: () => void;
}

function TrustedDeviceRecommendations({
  onOpenRecommendations,
}: TrustedDeviceRecommendationsProps): JSX.Element {
  return (
    <Card>
      <CardHeader>
        <h2 className="leading-none font-semibold tracking-tight">Recomendaciones</h2>
      </CardHeader>
      <CardContent className="space-y-6">
        {trustedDeviceRecommendationsPreview.length === 0 ? (
          <EmptyState
            description="Las recomendaciones de seguridad aparecerán aquí cuando estén disponibles."
            icon={ShieldQuestionMark}
            title="No hay recomendaciones disponibles."
          />
        ) : (
          <ul className="space-y-6" role="list">
            {trustedDeviceRecommendationsPreview.map((recommendation) => {
              const Icon = recommendation.icon;

              return (
                <li className="flex items-center justify-between gap-4" key={recommendation.title}>
                  <div className="flex items-start gap-4">
                    <div
                      aria-hidden="true"
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
                      <h3 className="text-sm font-semibold">{recommendation.title}</h3>

                      <p className="line-clamp-2 text-sm text-muted-foreground">
                        {recommendation.description}
                      </p>
                    </div>
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </CardContent>

      {trustedDeviceRecommendationsPreview.length > 0 && (
        <CardFooter className="mx-auto">
          <Button
            variant="link"
            className="text-blue-700 dark:text-blue-500"
            onClick={onOpenRecommendations}
          >
            Mas recomendaciones
            <ChevronRight className="h-4 w-4" />
          </Button>
        </CardFooter>
      )}
    </Card>
  );
}

export default TrustedDeviceRecommendations;
