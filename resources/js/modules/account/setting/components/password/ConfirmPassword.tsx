import type { Dispatch, SetStateAction } from "react";

import { Form } from "@inertiajs/react";

import { MdOutlineVpnKey } from "react-icons/md";

import { LoaderCircle } from "lucide-react";

import { store } from "@/shared/wayfinder/routes/password/confirm";

import InputError from "@/shared/components/form/InputError";
import { Button } from "@/shared/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/ui/dialog";
import { InputGroup, InputGroupAddon, InputGroupInput } from "@/shared/components/ui/input-group";
import { Label } from "@/shared/components/ui/label";

interface ConfirmPasswordProps {
  onConfirmed?: () => void;
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

export default function ConfirmPassword({ onConfirmed, open, setOpen }: ConfirmPasswordProps) {
  return (
    <Dialog onOpenChange={setOpen} open={open}>
      <DialogContent className="w-[30%]">
        <DialogHeader>
          <DialogTitle>Confirma tu contraseña</DialogTitle>
          <DialogDescription>
            Esta es una zona segura de la aplicación. Confirma tu contraseña antes de continuar.
          </DialogDescription>
        </DialogHeader>

        <Form
          {...store.form()}
          onSuccess={() => {
            setOpen(false);
            onConfirmed?.();
          }}
          resetOnSuccess={["password"]}
        >
          {({ errors, processing }) => (
            <div className="space-y-6">
              <div className="grid gap-2">
                <Label htmlFor="password">Contraseña</Label>

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

              <div className="flex items-center">
                <Button
                  className="w-full cursor-pointer"
                  data-test="confirm-password-button"
                  disabled={processing}
                >
                  {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                  Confirmar contraseña
                </Button>
              </div>
            </div>
          )}
        </Form>
      </DialogContent>
    </Dialog>
  );
}
