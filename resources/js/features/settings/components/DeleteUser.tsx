import { useRef } from "react";

import { Form } from "@inertiajs/react";

import ProfileController from "@/actions/App/Http/Controllers/user/ProfileController";

import InputError from "@/components/form/InputError";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogTitle,
  DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";

import { Badge } from "../../../components/ui/badge";

export default function DeleteUser() {
  const passwordInput = useRef<HTMLInputElement>(null);

  return (
    <Dialog>
      <DialogTrigger asChild>
        <Badge
          className="cursor-pointer bg-destructive hover:bg-destructive/90 dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
          data-test="delete-user-button"
        >
          Eliminar cuenta
        </Badge>
      </DialogTrigger>
      <DialogContent>
        <DialogTitle>Are you sure you want to delete your account?</DialogTitle>
        <DialogDescription>
          Once your account is deleted, all of its resources and data will also be permanently
          deleted. Please enter your password to confirm you would like to permanently delete your
          account.
        </DialogDescription>

        <Form
          {...ProfileController.destroy.form()}
          className="space-y-6"
          onError={() => passwordInput.current?.focus()}
          options={{
            preserveScroll: true,
          }}
          resetOnSuccess
        >
          {({ errors, processing, resetAndClearErrors }) => (
            <>
              <div className="grid gap-2">
                <Label className="sr-only" htmlFor="password">
                  Password
                </Label>

                <Input
                  id="password"
                  name="password"
                  type="password"
                  autoComplete="current-password"
                  placeholder="Password"
                  ref={passwordInput}
                />

                <InputError message={errors.password} />
              </div>

              <DialogFooter className="gap-2">
                <DialogClose asChild>
                  <Button
                    className="cursor-pointer"
                    onClick={() => resetAndClearErrors()}
                    variant="secondary"
                  >
                    Cancel
                  </Button>
                </DialogClose>

                <Button
                  className="cursor-pointer bg-destructive hover:bg-destructive/90 dark:bg-red-700 dark:text-white dark:hover:bg-red-800"
                  asChild
                  disabled={processing}
                >
                  <button type="submit" data-test="confirm-delete-user-button">
                    Delete account
                  </button>
                </Button>
              </DialogFooter>
            </>
          )}
        </Form>
      </DialogContent>
    </Dialog>
  );
}
