import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Password/Controllers/PasswordResetLinkController";

import { login } from "@/shared/wayfinder/routes";

import InputError from "@/shared/components/form/InputError";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Input } from "@/shared/components/shadcn/ui/input";
import { Label } from "@/shared/components/shadcn/ui/label";
import TextLink from "@/shared/components/TextLink";

interface ForgotPasswordProps {
  status?: string;
}

export default function ForgotPassword({ status }: ForgotPasswordProps) {
  return (
    <>
      <Head title="Restablecer contraseña" />

      {status && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
      )}

      <div className="space-y-6">
        <Form {...store.form()}>
          {({ processing, errors }) => (
            <>
              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>

                <Input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="off"
                  autoFocus
                  placeholder="email@example.com"
                />

                <InputError message={errors["email"]} />
              </div>

              <div className="my-6 flex items-center justify-start">
                <Button
                  className="w-full cursor-pointer"
                  data-test="email-password-reset-link-button"
                  disabled={processing}
                >
                  {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                  Enviar enlace para restablecer la contraseña
                </Button>
              </div>
            </>
          )}
        </Form>

        <div className="space-x-1 text-center text-sm text-muted-foreground">
          <span>O, volver a</span>

          <TextLink href={login()}>iniciar sesión</TextLink>
        </div>
      </div>
    </>
  );
}

ForgotPassword.layout = {
  title: "Restablecer contraseña",
  description:
    "Ingrese su correo electrónico para recibir un enlace para restablecer la contraseña",
};
