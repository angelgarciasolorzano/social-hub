import { Form, Head } from "@inertiajs/react";

import { LoaderCircle } from "lucide-react";

import EmailVerificationNotificationController from "@/actions/App/Auth/Email/Controllers/EmailVerificationNotificationController";

import { logout } from "@/routes";

import TextLink from "@/components/TextLink";
import { Button } from "@/components/ui/button";

import AuthCardLayout from "./layouts/AuthCardLayout";

export default function VerifyEmail({ status }: { status?: string }) {
  return (
    <AuthCardLayout
      description="Please verify your email address by clicking on the link we just emailed to you."
      title="Verify email"
    >
      <Head title="Email verification" />

      {status === "verification-link-sent" && (
        <div className="mb-4 text-center text-sm font-medium text-green-600">
          A new verification link has been sent to the email address you provided during
          registration.
        </div>
      )}

      <Form
        {...EmailVerificationNotificationController.store.form()}
        className="space-y-6 text-center"
      >
        {({ processing }) => (
          <>
            <Button disabled={processing} variant="secondary">
              {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
              Resend verification email
            </Button>

            <TextLink className="mx-auto block text-sm" href={logout()}>
              Log out
            </TextLink>
          </>
        )}
      </Form>
    </AuthCardLayout>
  );
}
