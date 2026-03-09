import type { Dispatch, SetStateAction } from "react";
import { useState } from "react";

import { Loader2Icon } from "lucide-react";

import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogClose,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";

import PublicationForm from "./components/PublicationForm";

interface PublicationProps {
  open: boolean;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function Publication({ open, setOpen }: PublicationProps) {
  const [processing, setProcessing] = useState<boolean>(false);

  return (
    <Dialog onOpenChange={setOpen} open={open}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Crear publicación</DialogTitle>

          <DialogDescription>Comparte tus ideas y pensamientos con el mundo.</DialogDescription>
        </DialogHeader>

        <PublicationForm setOpen={setOpen} setProcessing={setProcessing} />

        <DialogFooter>
          <DialogClose asChild>
            <Button type="button" variant="outline">
              Cancelar
            </Button>
          </DialogClose>

          <Button type="submit" disabled={processing} form="post-form">
            {processing ? <Loader2Icon className="h-4 w-4 animate-spin" /> : "Publicar"}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default Publication;
