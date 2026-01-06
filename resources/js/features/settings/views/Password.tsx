import { useRef } from "react";

import { Form } from "@inertiajs/react";

import { MdLockOutline, MdOutlineCheckCircle, MdOutlineVpnKey } from "react-icons/md";

import PasswordController from "@/actions/App/Http/Controllers/user/PasswordController";

import InputError from "@/components/form/InputError";
import LabelForm from "@/components/form/LabelForm";
import { Button } from "@/components/ui/button";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/components/ui/input-group";
import { Spinner } from "@/components/ui/spinner";

export default function Password() {
  const passwordInput = useRef<HTMLInputElement>(null);
  const currentPasswordInput = useRef<HTMLInputElement>(null);

  return (
    <div className="space-y-6">
      <Form
        {...PasswordController.update.form()}
        className="space-y-6"
        onError={(errors) => {
          if (errors.password) {
            passwordInput.current?.focus();
          }

          if (errors.current_password) {
            currentPasswordInput.current?.focus();
          }
        }}
        options={{
          preserveScroll: true,
        }}
        resetOnError={["password", "password_confirmation", "current_password"]}
        resetOnSuccess
      >
        {({ errors, processing }) => (
          <>
            <div className="grid gap-2">
              <LabelForm error={errors.current_password} htmlFor="current_password">
                Current password
              </LabelForm>

              <InputGroup className="dark:has-[[data-slot][aria-invalid=true]]:border-red-600">
                <InputGroupInput
                  id="current_password"
                  name="current_password"
                  type="password"
                  autoComplete="current-password"
                  aria-invalid={errors.current_password ? true : false}
                  placeholder="Current password"
                  ref={currentPasswordInput}
                />

                <InputGroupAddon>
                  <MdLockOutline />
                </InputGroupAddon>
              </InputGroup>

              <InputError message={errors.current_password} />
            </div>

            <div className="grid gap-2">
              <LabelForm error={errors.password} htmlFor="password">
                New password
              </LabelForm>

              <InputGroup className="dark:has-[[data-slot][aria-invalid=true]]:border-red-600">
                <InputGroupInput
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="new-password"
                  aria-invalid={errors.password ? true : false}
                  placeholder="New password"
                  ref={passwordInput}
                />

                <InputGroupAddon>
                  <MdOutlineVpnKey />
                </InputGroupAddon>
              </InputGroup>

              <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
              <LabelForm error={errors.password_confirmation} htmlFor="password_confirmation">
                Confirm password
              </LabelForm>

              <InputGroup className="dark:has-[[data-slot][aria-invalid=true]]:border-red-600">
                <InputGroupInput
                  id="password_confirmation"
                  name="password_confirmation"
                  type="password"
                  autoComplete="new-password"
                  aria-invalid={errors.password_confirmation ? true : false}
                  placeholder="Confirm password"
                />

                <InputGroupAddon>
                  <MdOutlineCheckCircle />
                </InputGroupAddon>
              </InputGroup>

              <InputError message={errors.password_confirmation} />
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
    </div>
  );
}
