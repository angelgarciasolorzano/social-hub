import type { JSX } from "react";

import { Form } from "@inertiajs/react";

import {
  CircleCheck,
  Info,
  LockKeyhole,
  ShieldAlert,
  ShieldCheck,
  SquareArrowOutUpRight,
} from "lucide-react";
import { toast } from "sonner";

import { enable } from "@/shared/wayfinder/routes/two-factor";

import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Badge } from "@/shared/components/shadcn/ui/badge";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Card, CardContent, CardHeader } from "@/shared/components/shadcn/ui/card";

import { useAppearance, useTranslation } from "@/shared/hooks";

import { alertVariants, iconColorVariants } from "@/shared/lib/styling";
import { cn } from "@/shared/lib/utils";

import { OptionCard } from "../components/ui/OptionCard";
import SummaryCard from "../components/ui/SummaryCard";
import Timeline from "../components/ui/Timeline";
import {
  twoFactorBenefits,
  twoFactorImportantDetails,
  twoFactorOperationSteps,
  twoFactorRecommendedApps,
  twoFactorRequirements,
} from "../data/twoFactorDisabled";

interface TwoFactorDisabledProps {
  hasSetupData: boolean;
  onActivate: () => void;
}

function TwoFactorDisabled({ hasSetupData, onActivate }: TwoFactorDisabledProps): JSX.Element {
  const { t } = useTranslation();

  const benefits = twoFactorBenefits.map((benefit) => ({
    ...benefit,
    title: t(benefit.title),
    description: t(benefit.description),
  }));
  const importantDetails = twoFactorImportantDetails.map((importantDetail) => ({
    ...importantDetail,
    title: t(importantDetail.title),
    description: t(importantDetail.description),
  }));

  return (
    <div className="flex flex-col gap-6">
      <div className="flex min-w-0 flex-col gap-6 xl:flex-row">
        <div className="flex min-w-0 flex-1 flex-col gap-6 xl:gap-8">
          <TwoFactorTitle hasSetupData={hasSetupData} onActivate={onActivate} />
          <TwoFactorInfoBanner />
          <OptionCard
            options={benefits}
            title={t("setting/two-factor.disabled_view.benefits_title")}
          />
          <SummaryCard
            title={t("setting/two-factor.disabled_view.important_details_title")}
            data={importantDetails}
          />
        </div>

        <div className="flex w-full min-w-0 flex-col gap-6 xl:max-w-sm xl:shrink-0">
          <TwoFactorOperations />
          <TwoFactorRequirements />
          <TwoFactorRecommendedApps />
        </div>
      </div>

      <TwoFactorActivationForm />
    </div>
  );
}

interface TwoFactorTitleProps {
  hasSetupData: boolean;
  onActivate: () => void;
}

function TwoFactorTitle({ hasSetupData, onActivate }: TwoFactorTitleProps): JSX.Element {
  const { t } = useTranslation();
  const { resolvedAppearance } = useAppearance();

  return (
    <div className="flex flex-col items-start gap-4 rounded-xl border bg-card p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:gap-6 sm:p-6">
      <div className="flex min-w-0 items-start gap-4">
        <div className={cn(iconColorVariants.violet.iconBgClass, "rounded-3xl p-2")}>
          <ShieldCheck className={cn("h-12 w-12", iconColorVariants.violet.iconFgClass)} />
        </div>

        <div className="flex flex-col gap-2">
          <h1 className="text-xl font-semibold tracking-tight sm:text-2xl">
            {t("setting/two-factor.disabled_view.title")}
          </h1>

          <p className="text-sm text-muted-foreground">
            {t("setting/two-factor.disabled_view.description")}
          </p>

          <Badge
            variant={resolvedAppearance === "light" ? "destructive" : null}
            className="inline-flex dark:bg-red-700 dark:text-white"
          >
            <ShieldAlert />
            {t("setting/two-factor.disabled_view.status")}
          </Badge>
        </div>
      </div>

      <div className="w-full sm:w-auto">
        {hasSetupData ? (
          <Button onClick={onActivate} className="w-full cursor-pointer sm:w-auto">
            <ShieldCheck />
            {t("setting/two-factor.disabled_view.continue_setup")}
          </Button>
        ) : (
          <Form
            {...enable.form()}
            onError={() => toast.error(t("setting/two-factor.disabled_view.activate_failed"))}
            onSuccess={onActivate}
            className="w-full cursor-pointer sm:w-auto"
          >
            {({ processing }) => (
              <Button
                type="submit"
                className="w-full cursor-pointer sm:w-auto"
                disabled={processing}
              >
                {processing
                  ? t("setting/two-factor.disabled_view.activating")
                  : t("setting/two-factor.disabled_view.activate")}
              </Button>
            )}
          </Form>
        )}
      </div>
    </div>
  );
}

function TwoFactorInfoBanner(): JSX.Element {
  const { t } = useTranslation();

  return (
    <Alert className={alertVariants.info}>
      <Info />
      <AlertTitle>{t("setting/two-factor.disabled_view.pin_title")}</AlertTitle>
      <AlertDescription>{t("setting/two-factor.disabled_view.pin_description")}</AlertDescription>
    </Alert>
  );
}

function TwoFactorActivationForm(): JSX.Element {
  const { t } = useTranslation();

  return (
    <div className="flex flex-col items-start gap-4 rounded-xl border p-5 shadow-xs sm:flex-row sm:items-center sm:justify-between sm:p-6">
      <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        <ShieldAlert className="h-4 w-4 text-muted-foreground" />
        <span className="text-muted-foreground">
          {t("setting/two-factor.disabled_view.questions")}
        </span>

        <span className="font-medium text-blue-700 dark:text-blue-500">
          {t("setting/two-factor.disabled_view.faq_link")}
        </span>
      </div>

      <div className="flex items-center gap-2">
        <LockKeyhole className="h-4 w-4 text-muted-foreground" />
        <span className="text-sm text-muted-foreground">
          {t("setting/two-factor.disabled_view.security_priority")}
        </span>
      </div>
    </div>
  );
}

function TwoFactorOperations(): JSX.Element {
  const { t } = useTranslation();

  const operationSteps = twoFactorOperationSteps.map((operationStep) => ({
    ...operationStep,
    title: t(operationStep.title),
    description: t(operationStep.description),
  }));

  return (
    <Card className="shadow-xs">
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold">
          {t("setting/two-factor.disabled_view.how_it_works")}
        </h2>
      </CardHeader>
      <CardContent>
        <Timeline steps={operationSteps} />
      </CardContent>
    </Card>
  );
}

function TwoFactorRequirements(): JSX.Element {
  const { t } = useTranslation();

  return (
    <Card className="shadow-xs">
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold">
          {t("setting/two-factor.disabled_view.requirements_title")}
        </h2>
      </CardHeader>
      <CardContent>
        <div className="space-y-3 text-sm">
          {twoFactorRequirements.map((requirement) => (
            <div key={requirement.key} className="flex items-center gap-2">
              <CircleCheck className="text-green-500" />
              <p>{t(requirement.description)}</p>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  );
}

function TwoFactorRecommendedApps(): JSX.Element {
  const { t } = useTranslation();

  return (
    <Card className="shadow-xs">
      <CardHeader>
        <h2 className="text-lg leading-none font-semibold">
          {t("setting/two-factor.disabled_view.recommended_apps")}
        </h2>
      </CardHeader>
      <CardContent>
        <div className="space-y-5 text-sm font-medium">
          {twoFactorRecommendedApps.map((app) => {
            const Icon = app.icon;
            const IconDark = app.iconDark;

            return (
              <div key={app.key} className="flex items-center justify-between gap-2">
                <div className="flex items-center gap-4">
                  {IconDark ? (
                    <>
                      <Icon className="h-6 w-6 dark:hidden" />
                      <IconDark className="hidden h-6 w-6 dark:block" />
                    </>
                  ) : (
                    <Icon className="h-6 w-6" />
                  )}
                  <span>{app.name}</span>
                </div>

                <SquareArrowOutUpRight className="h-4 w-4 text-muted-foreground" />
              </div>
            );
          })}
        </div>
      </CardContent>
    </Card>
  );
}

export default TwoFactorDisabled;
