import { Form, Link, usePage } from "@inertiajs/react";

import { Transition } from "@headlessui/react";

import UserController from "@/actions/App/User/Controllers/UserController";

import { send } from "@/routes/verification";

import InputError from "@/shared/components/form/InputError";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import { type SharedData } from "@/types";

export default function SettingsProfileView() {
  const { auth } = usePage<SharedData>().props;
  const { mustVerifyEmail, sessionStatus } = auth.user;

  return (
    <>
      <div className="space-y-6">
        <Form
          {...UserController.update.form(auth.user.id)}
          className="space-y-6"
          options={{
            preserveScroll: true,
          }}
        >
          {({ errors, processing, recentlySuccessful }) => (
            <>
              <div className="grid gap-2">
                <Label htmlFor="name">Name</Label>

                <Input
                  id="name"
                  name="name"
                  autoComplete="name"
                  className="mt-1 block w-full"
                  defaultValue={auth.user.name}
                  placeholder="Full name"
                  required
                />

                <InputError className="mt-2" message={errors.name} />
              </div>

              <div className="grid gap-2">
                <Label htmlFor="email">Email address</Label>

                <Input
                  id="email"
                  name="email"
                  type="email"
                  autoComplete="username"
                  className="mt-1 block w-full"
                  defaultValue={auth.user.email}
                  placeholder="Email address"
                  required
                />

                <InputError className="mt-2" message={errors.email} />
              </div>

              {mustVerifyEmail && auth.user.email_verified_at === null && (
                <div>
                  <p className="-mt-4 text-sm text-muted-foreground">
                    Your email address is unverified.{" "}
                    <Link
                      className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                      as="button"
                      href={send()}
                    >
                      Click here to resend the verification email.
                    </Link>
                  </p>

                  {sessionStatus === "verification-link-sent" && (
                    <div className="mt-2 text-sm font-medium text-green-600">
                      A new verification link has been sent to your email address.
                    </div>
                  )}
                </div>
              )}

              <div className="flex items-center gap-4">
                <Button data-test="update-profile-button" disabled={processing}>
                  Save
                </Button>

                <Transition
                  enter="transition ease-in-out"
                  enterFrom="opacity-0"
                  leave="transition ease-in-out"
                  leaveTo="opacity-0"
                  show={recentlySuccessful}
                >
                  <p className="text-sm text-neutral-600">Saved</p>
                </Transition>
              </div>
            </>
          )}
        </Form>
      </div>
    </>
  );
}
