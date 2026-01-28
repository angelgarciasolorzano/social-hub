import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import NewPasswordController from "@/actions/App/Auth/Password/Controllers/NewPasswordController";

import InputError from "@/components/form/InputError";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import AuthCardLayout from "../layouts/AuthCardLayout";

interface ResetPasswordProps {
  email: string;
  token: string;
}

export default function ResetPassword({ token, email }: ResetPasswordProps) {
  return (
    <AuthCardLayout description="Please enter your new password below" title="Reset password">
      <Head title="Reset password" />

      <Form
        {...NewPasswordController.store.form()}
        resetOnSuccess={["password", "password_confirmation"]}
        transform={(data) => ({ ...data, token, email })}
      >
        {({ processing, errors }) => (
          <div className="grid gap-6">
            <div className="grid gap-2">
              <Label htmlFor="email">Email</Label>
              <Input
                id="email"
                name="email"
                type="email"
                autoComplete="email"
                className="mt-1 block w-full"
                readOnly
                value={email}
              />
              <InputError className="mt-2" message={errors.email} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password">Password</Label>
              <Input
                id="password"
                name="password"
                type="password"
                autoComplete="new-password"
                className="mt-1 block w-full"
                autoFocus
                placeholder="Password"
              />
              <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
              <Label htmlFor="password_confirmation">Confirm password</Label>
              <Input
                id="password_confirmation"
                name="password_confirmation"
                type="password"
                autoComplete="new-password"
                className="mt-1 block w-full"
                placeholder="Confirm password"
              />
              <InputError className="mt-2" message={errors.password_confirmation} />
            </div>

            <Button
              type="submit"
              className="mt-4 w-full"
              data-test="reset-password-button"
              disabled={processing}
            >
              {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
              Reset password
            </Button>
          </div>
        )}
      </Form>
    </AuthCardLayout>
  );
}
