import { type JSX, type SubmitEvent, useId } from "react";

import { useForm } from "@inertiajs/react";

import { ShieldBan, ShieldQuestionMark } from "lucide-react";

import TwoFactorCodeField from "@/modules/setting/shared/components/TwoFactorCodeField";
import { useDialogFocusRestoration } from "@/modules/setting/shared/hooks/useDialogFocusRestoration";

import { destroy } from "@/shared/wayfinder/routes/setting/security/two-factor-authentication";

import { InputError, LabelForm, PasswordInput } from "@/shared/components/form";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
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
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { useTranslation } from "@/shared/hooks";

import { alertVariants } from "@/shared/lib/styling";

interface DisableTwoFactorFormData {
  password: string;
  code: string;
}

interface DisableTwoFactorDialogProps {
  isOpen: boolean;
  setOpen: (open: boolean) => void;
}

function DisabledTwoFactorDialog({ isOpen, setOpen }: DisableTwoFactorDialogProps): JSX.Element {
  const { t } = useTranslation();
  const idPrefix = useId();
  const formId = `${idPrefix}-disable-two-factor-form`;
  const passwordInputId = `${idPrefix}-password`;
  const passwordErrorId = `${idPrefix}-password-error`;
  const codeFieldId = `${idPrefix}-code`;
  const dialogFocusRestoration = useDialogFocusRestoration();

  const { data, setData, processing, errors, reset, submit, clearErrors } =
    useForm<DisableTwoFactorFormData>({
      password: "",
      code: "",
    });

  const handleSubmit = (e: SubmitEvent<HTMLFormElement>): void => {
    e.preventDefault();

    submit(destroy(), {
      onSuccess: () => {
        setOpen(false);
        reset();
      },
      onError: () => {
        setData("code", "");
      },
      preserveState: true,
    });
  };

  return (
    <Dialog
      open={isOpen}
      onOpenChange={(nextOpen) => {
        if (processing && !nextOpen) {
          return;
        }

        setOpen(nextOpen);
      }}
    >
      <DialogContent {...dialogFocusRestoration}>
        <DialogHeader>
          <DialogTitle asChild>
            <h2 className="flex items-center gap-2">
              <ShieldBan className="h-5 w-5 text-muted-foreground" />
              {t("setting/two-factor.disable_dialog.title")}
            </h2>
          </DialogTitle>
          <DialogDescription>
            {t("setting/two-factor.disable_dialog.description")}
          </DialogDescription>
        </DialogHeader>

        <Alert className={alertVariants.info}>
          <ShieldQuestionMark />

          <AlertTitle className="line-clamp-4 font-normal">
            {t("setting/two-factor.disable_dialog.warning_title")}
          </AlertTitle>

          <AlertDescription>
            {t("setting/two-factor.disable_dialog.warning_description")}
          </AlertDescription>
        </Alert>

        <form id={formId} className="grid gap-6" onSubmit={handleSubmit}>
          <div className="flex flex-col gap-2">
            <LabelForm error={errors.password} htmlFor={passwordInputId}>
              {t("setting/two-factor.labels.password")}
            </LabelForm>

            <PasswordInput
              id={passwordInputId}
              name="password"
              autoComplete="current-password"
              onChange={(event) => {
                setData("password", event.target.value);
              }}
              aria-invalid={!!errors.password}
              aria-describedby={errors.password ? passwordErrorId : undefined}
              required
              placeholder={t("setting/two-factor.labels.password")}
              value={data.password}
            />

            <InputError id={passwordErrorId} message={errors.password} />
          </div>

          <TwoFactorCodeField
            id={codeFieldId}
            name="code"
            value={data.code}
            error={errors.code}
            disabled={processing}
            onChange={(value) => {
              setData("code", value);
            }}
            onModeChange={clearErrors}
          />
        </form>

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline" disabled={processing}>
              {t("setting/common.actions.cancel")}
            </Button>
          </DialogClose>

          <Button type="submit" form={formId} disabled={processing}>
            {processing ? (
              <>
                <Spinner />
                {t("setting/two-factor.disable_dialog.submitting")}
              </>
            ) : (
              t("setting/two-factor.labels.disable")
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default DisabledTwoFactorDialog;
