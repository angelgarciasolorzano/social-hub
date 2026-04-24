import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Login/Controllers/AuthenticatedSessionController";

import { register } from "@/shared/wayfinder/routes";
import { request } from "@/shared/wayfinder/routes/password";

import { InputError } from "@/shared/components/form";
import TextLink from "@/shared/components/TextLink";
import { Button } from "@/shared/components/ui/button";
import { Checkbox } from "@/shared/components/ui/checkbox";
import { Input } from "@/shared/components/ui/input";
import { Label } from "@/shared/components/ui/label";

import AuthCardLayout from "../layouts/AuthCardLayout";

interface LoginProps {
  canResetPassword?: boolean;
  status?: string;
}

function Login({ status, canResetPassword }: LoginProps) {
  return (
    <AuthCardLayout
      description="Ingrese su correo electrónico y contraseña a continuación para iniciar sesión"
      title="Inicia sesión en tu cuenta"
    >
      <Head title="Inicia sesión" />

      <Form {...store.form()} className="flex flex-col gap-6" resetOnSuccess={["password"]}>
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>

                <Input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  autoFocus
                  placeholder="email@example.com"
                  required
                  tabIndex={1}
                />

                <InputError message={errors["email"]} />
              </div>

              <div className="grid gap-2">
                <div className="flex items-center">
                  <Label htmlFor="password">Contraseña</Label>

                  {canResetPassword && (
                    <TextLink
                      className="ml-auto text-sm"
                      href={request()}
                      tabIndex={5}
                      viewTransition
                    >
                      ¿Olvidaste tu contraseña?
                    </TextLink>
                  )}
                </div>

                <Input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  placeholder="**********"
                  required
                  tabIndex={2}
                />

                <InputError message={errors["password"]} />
              </div>

              <div className="flex items-center space-x-3">
                <Checkbox id="remember" name="remember" tabIndex={3} />

                <Label htmlFor="remember">Recuérdame</Label>
              </div>

              <Button
                type="submit"
                className="mt-4 w-full cursor-pointer"
                data-test="login-button"
                disabled={processing}
                tabIndex={4}
              >
                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                Iniciar sesión
              </Button>
            </div>

            <div className="text-center text-sm text-muted-foreground">
              ¿No tienes una cuenta?{" "}
              <TextLink href={register()} tabIndex={5} viewTransition>
                Regístrate
              </TextLink>
            </div>
          </>
        )}
      </Form>

      {status && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">{status}</div>
      )}
    </AuthCardLayout>
  );
}

export default Login;
