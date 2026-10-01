import type { JSX } from "react";
import { useRef } from "react";

import { Form } from "@inertiajs/react";

import { CircleAlert } from "lucide-react";

import { update } from "@/shared/wayfinder/actions/App/Auth/Modules/Password/Controllers/PasswordController";

import { PasswordInput } from "@/shared/components/form";
import InputError from "@/shared/components/form/InputError";
import LabelForm from "@/shared/components/form/LabelForm";
import { Alert, AlertDescription, AlertTitle } from "@/shared/components/shadcn/ui/alert";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Spinner } from "@/shared/components/shadcn/ui/spinner";

import { alertVariants } from "@/shared/lib/styling";

export default function EditPassword(): JSX.Element {
  const passwordInput = useRef<HTMLInputElement>(null);
  const currentPasswordInput = useRef<HTMLInputElement>(null);

  return (
    <>
      <Form
        {...update.form()}
        className="space-y-6"
        onError={(errors) => {
          if (errors["password"]) {
            passwordInput.current?.focus();
          }

          if (errors["current_password"]) {
            currentPasswordInput.current?.focus();
          }
        }}
        resetOnSuccess
      >
        {({ errors, processing }) => (
          <>
            <Alert className={alertVariants.warning} role="note">
              <CircleAlert aria-hidden="true" />
              <AlertTitle>Trusted devices will be revoked</AlertTitle>
              <AlertDescription>
                Changing your password will revoke the trusted status of every device currently
                marked as trusted on your account. If two-factor authentication is enabled, you will
                need to verify again the next time you sign in from those devices. This will not
                sign out sessions that are already open.
              </AlertDescription>
            </Alert>

            <div className="grid gap-2">
              <LabelForm error={errors["current_password"]} htmlFor="current_password">
                Current password
              </LabelForm>

              <PasswordInput
                id="current_password"
                name="current_password"
                autoComplete="current-password"
                aria-invalid={!!errors["current_password"]}
                placeholder="Current password"
                ref={currentPasswordInput}
              />

              <InputError message={errors["current_password"]} />
            </div>

            <div className="grid gap-2">
              <LabelForm error={errors["password"]} htmlFor="password">
                New password
              </LabelForm>

              <PasswordInput
                id="password"
                name="password"
                autoComplete="new-password"
                aria-invalid={!!errors["password"]}
                placeholder="New password"
                ref={passwordInput}
              />

              <InputError message={errors["password"]} />
            </div>

            <div className="grid gap-2">
              <LabelForm error={errors["password_confirmation"]} htmlFor="password_confirmation">
                Confirm password
              </LabelForm>

              <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autoComplete="new-password"
                aria-invalid={!!errors["password_confirmation"]}
                placeholder="Confirm password"
              />

              <InputError message={errors["password_confirmation"]} />
            </div>

            <div className="flex items-center gap-4">
              <Button data-test="update-password-button" disabled={processing}>
                {processing && <Spinner />}
                Save password
              </Button>
            </div>
          </>
        )}
      </Form>
    </>
  );
}
