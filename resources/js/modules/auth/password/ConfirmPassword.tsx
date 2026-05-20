import { Form, Head } from "@inertiajs/react";

import { store } from "@/shared/wayfinder/routes/password/confirm";

import { InputError, LabelForm, PasswordInput } from "@/shared/components/form";
import { Button } from "@/shared/components/ui/button";
import { Spinner } from "@/shared/components/ui/spinner";

export default function ConfirmPassword() {
  return (
    <>
      <Head title="Confirm password" />

      <Form
        {...store.form()}
        options={{
          replace: true,
          preserveState: false,
        }}
        resetOnSuccess={["password"]}
      >
        {({ processing, errors }) => (
          <div className="space-y-6">
            <div className="grid gap-2">
              <LabelForm error={errors["password"]} htmlFor="password">
                Password
              </LabelForm>

              <PasswordInput
                id="password"
                name="password"
                autoComplete="current-password"
                autoFocus
                placeholder="Password"
              />

              <InputError message={errors["password"]} />
            </div>

            <div className="flex items-center">
              <Button className="w-full" data-test="confirm-password-button" disabled={processing}>
                {processing && <Spinner />}
                Confirm password
              </Button>
            </div>
          </div>
        )}
      </Form>
    </>
  );
}

ConfirmPassword.layout = {
  title: "Confirm your password",
  description:
    "This is a secure area of the application. Please confirm your password before continuing.",
};
