import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/shared/components/shadcn/ui/dialog";

import { cn } from "@/shared/lib/utils";

interface ImageUploadModalProps {
  preview: string;
  type: "profile" | "cover";
  onClose: () => void;
  onConfirm: () => void;
}

function ProfileImageUploadDialog({ preview, type, onClose, onConfirm }: ImageUploadModalProps) {
  return (
    <Dialog onOpenChange={onClose} open>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Vista previa</DialogTitle>

          <DialogDescription>
            Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nullam egestas, urna in
            tincidunt venenatis, nisi risus tincidunt nisi,
          </DialogDescription>
        </DialogHeader>

        <div className="flex justify-center">
          <img
            className={cn(
              "object-cover",
              type === "profile" ? "h-72 w-72 rounded-full" : "max-h-100 rounded-md",
            )}
            alt="Preview"
            src={preview}
          />
        </div>

        <DialogFooter>
          <Button onClick={onClose} variant="outline">
            Cancelar
          </Button>
          <Button onClick={onConfirm}>Confirmar</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

export default ProfileImageUploadDialog;
