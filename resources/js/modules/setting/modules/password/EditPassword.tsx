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
              <AlertTitle>Se revocará la confianza de tus dispositivos</AlertTitle>
              <AlertDescription>
                Al cambiar tu contraseña, se revocará la confianza de todos los dispositivos
                asociados a tu cuenta. Si tienes activada la autenticación en dos pasos, tendrás que
                verificarte de nuevo la próxima vez que inicies sesión desde esos dispositivos. Las
                sesiones que ya estén abiertas seguirán activas.
              </AlertDescription>
            </Alert>

            <div className="grid gap-2">
              <LabelForm error={errors["current_password"]} htmlFor="current_password">
                Contraseña actual
              </LabelForm>

              <PasswordInput
                id="current_password"
                name="current_password"
                autoComplete="current-password"
                aria-invalid={!!errors["current_password"]}
                placeholder="Contraseña actual"
                ref={currentPasswordInput}
              />

              <InputError message={errors["current_password"]} />
            </div>

            <div className="grid gap-2">
              <LabelForm error={errors["password"]} htmlFor="password">
                Nueva contraseña
              </LabelForm>

              <PasswordInput
                id="password"
                name="password"
                autoComplete="new-password"
                aria-invalid={!!errors["password"]}
                placeholder="Nueva contraseña"
                ref={passwordInput}
              />

              <InputError message={errors["password"]} />
            </div>

            <div className="grid gap-2">
              <LabelForm error={errors["password_confirmation"]} htmlFor="password_confirmation">
                Confirmar contraseña
              </LabelForm>

              <PasswordInput
                id="password_confirmation"
                name="password_confirmation"
                autoComplete="new-password"
                aria-invalid={!!errors["password_confirmation"]}
                placeholder="Confirmar contraseña"
              />

              <InputError message={errors["password_confirmation"]} />
            </div>

            <div className="flex items-center gap-4">
              <Button data-test="update-password-button" disabled={processing}>
                {processing && <Spinner />}
                Guardar contraseña
              </Button>
            </div>
          </>
        )}
      </Form>
    </>
  );
}
