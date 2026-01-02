import RegisteredUserController from '@/actions/App/Auth/Register/Controllers/RegisteredUserController';
import { login } from '@/routes';
import { Form, Head } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';

import InputError from '@/components/form/InputError';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthLayout from '@/layouts/auth-layout';

function Register() {
  return (
    <AuthLayout
      title="Crea una cuenta"
      description="Ingrese sus datos a continuación para crear su cuenta"
    >
      <Head title="Register" />
        
      <Form
        {...RegisteredUserController.store.form()}
        resetOnSuccess={['password', 'password_confirmation']}
        disableWhileProcessing
        className="flex flex-col gap-6"
      >
        {({ processing, errors }) => (
          <>
            <div className="grid gap-6">
              <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>

                <Input
                  id="name"
                  type="text"
                  required
                  autoFocus
                  tabIndex={1}
                  autoComplete="name"
                  name="name"
                  placeholder="Full name"
                />

                <InputError message={errors.name} className="mt-2" />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="email">Correo electrónico</Label>

                <Input
                  id="email"
                  type="email"
                  required
                  tabIndex={2}
                  autoComplete="email"
                  name="email"
                  placeholder="email@example.com"
                />

                <InputError message={errors.email} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="password">Contraseña</Label>

                <Input
                  id="password"
                  type="password"
                  required
                  tabIndex={3}
                  autoComplete="new-password"
                  name="password"
                  placeholder="Password"
                />

                <InputError message={errors.password} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="password_confirmation">
                  Confirmar contraseña
                </Label>

                <Input
                  id="password_confirmation"
                  type="password"
                  required
                  tabIndex={4}
                  autoComplete="new-password"
                  name="password_confirmation"
                  placeholder="Confirm password"
                />

                <InputError message={errors.password_confirmation} />
              </div>

              <Button
                type="submit"
                className="mt-2 w-full cursor-pointer"
                tabIndex={5}
                data-test="register-user-button"
              >
                {processing && (
                  <LoaderCircle className="h-4 w-4 animate-spin" />
                )}

                Crear cuenta
              </Button>
            </div>

            <div className="text-center text-sm text-muted-foreground">
              ¿Ya tienes una cuenta?{' '}

              <TextLink href={login()} tabIndex={6}>
                Iniciar sesión
              </TextLink>
            </div>
          </>
        )}
      </Form>
    </AuthLayout>
  );
}

export default Register;
