import { Dispatch, SetStateAction } from "react";

import { Form } from "@inertiajs/react";

import PostController from "@/actions/App/Post/Controllers/PostController";

import InputError from "@/components/form/InputError";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";

interface PublicationFormContentProps {
  setOpen: Dispatch<SetStateAction<boolean>>;
  setProcessing: Dispatch<SetStateAction<boolean>>;
}

function PublicationForm({ setOpen, setProcessing }: PublicationFormContentProps) {
  return (
    <Form
      {...PostController.form()}
      id="post-form"
      className="flex flex-col gap-5"
      onFinish={() => setProcessing(false)}
      onStart={() => setProcessing(true)}
      onSuccess={() => setOpen(false)}
    >
      {({ errors }) => (
        <>
          <div className="flex flex-col gap-2">
            <Label htmlFor="post-content">Contenido</Label>

            <Textarea
              id="post-content"
              name="content"
              className="min-h-32"
              minLength={10}
              placeholder="Escribe lo que quieres compartir"
              required
            />

            <InputError message={errors.content} />
          </div>

          <div className="flex flex-col gap-2">
            <Label htmlFor="post-image">Imagen</Label>

            <Input id="post-image" name="image_file" type="file" accept="image/*" />

            <InputError message={errors.image_file} />
          </div>
        </>
      )}
    </Form>
  );
}

export default PublicationForm;
