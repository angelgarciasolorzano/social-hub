import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import { store } from "@/shared/wayfinder/actions/App/Auth/Register/Controllers/RegisteredUserController";

import { login } from "@/shared/wayfinder/routes";

import InputError from "@/shared/components/form/InputError";
import { Button } from "@/shared/components/shadcn/ui/button";
import { Input } from "@/shared/components/shadcn/ui/input";
import { Label } from "@/shared/components/shadcn/ui/label";
import TextLink from "@/shared/components/TextLink";

export default function Register() {
  return (
    <>
      <Head title="Register" />

      <Form
        {...store.form()}
        className="flex flex-col gap-6"
        disableWhileProcessing
        resetOnSuccess={["password", "password_confirmation"]}
      >
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>

                <Input
                  id="name"
                  name="name"
                  type="text"
                  autoComplete="name"
                  autoFocus
                  placeholder="Full name"
                  required
                  tabIndex={1}
                />

                <InputError className="mt-2" message={errors["name"]} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>

                <Input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="email"
                  placeholder="email@example.com"
                  required
                  tabIndex={2}
                />

                <InputError message={errors["email"]} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="password">Contraseña</Label>

                <Input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  placeholder="Password"
                  required
                  tabIndex={3}
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
                  placeholder="Confirm password"
                  required
                  tabIndex={4}
                />

                <InputError message={errors["password_confirmation"]} />
              </div>

              <Button
                type="submit"
                className="mt-2 w-full cursor-pointer"
                data-test="register-user-button"
                tabIndex={5}
              >
                {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                Crear cuenta
              </Button>
            </div>

            <div className="text-center text-sm text-muted-foreground">
              ¿Ya tienes una cuenta?{" "}
              <TextLink href={login()} tabIndex={6} viewTransition>
                Iniciar sesión
              </TextLink>
            </div>
          </>
        )}
      </Form>
    </>
  );
}

Register.layout = {
  title: "Crea una cuenta",
  description: "Ingrese sus datos a continuación para crear su cuenta",
};
