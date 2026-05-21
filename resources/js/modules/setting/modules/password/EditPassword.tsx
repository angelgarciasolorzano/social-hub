import { useRef } from "react";

import { Form } from "@inertiajs/react";

import { update } from "@/shared/wayfinder/actions/App/Auth/Password/Controllers/PasswordController";

import { PasswordInput } from "@/shared/components/form";
import InputError from "@/shared/components/form/InputError";
import LabelForm from "@/shared/components/form/LabelForm";
import { Button } from "@/shared/components/ui/button";
import { Spinner } from "@/shared/components/ui/spinner";

export default function EditPassword() {
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
