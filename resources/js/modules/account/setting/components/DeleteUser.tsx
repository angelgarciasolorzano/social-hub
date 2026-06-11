import { useRef } from "react";

import { Form, usePage } from "@inertiajs/react";

import { MdOutlineVpnKey } from "react-icons/md";

import { destroy } from "@/shared/wayfinder/actions/App/User/Controllers/UserController";

import { LabelForm } from "@/shared/components/form";
import InputError from "@/shared/components/form/InputError";
import { Badge } from "@/shared/components/ui/badge";
import { Button } from "@/shared/components/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogTitle,
  DialogTrigger,
} from "@/shared/components/ui/dialog";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/shared/components/ui/input-group";

import type { SharedData } from "@/shared/types";

export default function DeleteUser() {
  const passwordInput = useRef<HTMLInputElement>(null);
  const { auth } = usePage<SharedData>().props;

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
        <DialogTitle>¿Estás seguro/a de que quieres eliminar tu cuenta?</DialogTitle>

        <DialogDescription>
          Una vez que tu cuenta sea eliminada, todos sus recursos y datos también serán eliminados
          permanentemente. Por favor, ingresa tu contraseña para confirmar que deseas eliminar
          permanentemente tu cuenta.
        </DialogDescription>

        <Form
          {...destroy.form(auth.user.id)}
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
                <LabelForm className="sr-only" error={errors["password"]} htmlFor="password">
                  Password
                </LabelForm>

                <InputGroup>
                  <InputGroupInput
                    id="password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    placeholder="Contraseña"
                  />

                  <InputGroupAddon>
                    <MdOutlineVpnKey />
                  </InputGroupAddon>
                </InputGroup>

                <InputError message={errors["password"]} />
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
