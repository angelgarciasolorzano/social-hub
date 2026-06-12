import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Email/Controllers/EmailVerificationNotificationController";

import { logout } from "@/shared/wayfinder/routes";

import { Button } from "@/shared/components/shadcn/ui/button";
import TextLink from "@/shared/components/TextLink";

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <>
      <Head title="Email verification" />

      {status === "verification-link-sent" && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">
          Se ha enviado un nuevo enlace de verificación a la dirección de correo electrónico que
          proporcionaste durante el registro.
        </div>
      )}

      <Form {...store.form()} className="space-y-6 text-center">
        {({ processing }) => (
          <>
            <Button disabled={processing} variant="secondary">
              {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
              Reenviar correo de verificación
            </Button>

            <TextLink className="mx-auto block text-sm" href={logout()} viewTransition>
              Cerrar sesión
            </TextLink>
          </>
        )}
      </Form>
    </>
  );
}

VerifyEmail.layout = {
  title: "Verificar correo electrónico",
  description:
    "Por favor, verifica tu dirección de correo electrónico haciendo clic en el enlace que acabamos de enviarte.",
};
