import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Modules/Password/Controllers/PasswordNewController";

import InputError from "@/shared/components/form/InputError";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Input } from "@/shared/components/shadcn/ui/input";
import { Label } from "@/shared/components/shadcn/ui/label";

interface ResetPasswordProps {
  email: string;
  token: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
  return (
    <>
      <Head title="Restablecer contraseña" />

      <Form
        {...store.form()}
        resetOnSuccess={["password", "password_confirmation"]}
        transform={(data) => ({ ...data, token, email })}
      >
        {({ processing, errors }) => (
          <div className="grid gap-6">
            <div className="grid gap-2">
              <Label htmlFor="email">Correo electrónico</Label>

              <Input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                className="mt-1 block w-full"
                readOnly
                value={email}
              />

              <InputError className="mt-2" message={errors["email"]} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">Contraseña</Label>

              <Input
                id="password"
                name="password"
                type="password"
                autoComplete="new-password"
                className="mt-1 block w-full"
                autoFocus
                placeholder="Nueva contraseña"
              />

              <InputError message={errors["password"]} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">Confirmar contraseña</Label>

              <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autoComplete="new-password"
                className="mt-1 block w-full"
                placeholder="Confirmar contraseña"
              />

              <InputError className="mt-2" message={errors["password_confirmation"]} />
            </div>

            <Button
              type="submit"
              className="mt-4 w-full"
              data-test="reset-password-button"
              disabled={processing}
            >
              {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
              Restablecer contraseña
            </Button>
          </div>
        )}
      </Form>
    </>
  );
}

ResetPassword.layout = {
  title: "Restablecer contraseña",
  description: "Ingrese su nueva contraseña a continuación",
};
