import type { Dispatch, FormEvent, SetStateAction } from "react";

import type { InertiaFormProps } from "@inertiajs/react";

import PostController from "@/shared/wayfinder/actions/App/Post/Controllers/PostController";

import { LabelForm } from "@/shared/components/form";
import InputError from "@/shared/components/form/InputError";
import { Input } from "@/shared/components/ui/input";
import { Textarea } from "@/shared/components/ui/textarea";

import type { PostFormData } from "../../types/post";

interface PostFormtProps {
  form: InertiaFormProps<PostFormData>;
  setOpen: Dispatch<SetStateAction<boolean>>;
}

function PostForm({ form, setOpen }: PostFormtProps) {
  const { submit, errors, setData } = form;

  const handleSubmit = (e: FormEvent<HTMLFormElement>): void => {
    e.preventDefault();

    submit(PostController(), {
      onSuccess: () => {
        setOpen(false);
        form.reset();
      },
      reset: ["posts"],
    });
  };

  return (
    <form id="post-form" className="flex flex-col gap-5" onSubmit={handleSubmit}>
      <>
        <div className="flex flex-col gap-2">
          <LabelForm error={errors.content} htmlFor="post-content">
            Contenido
          </LabelForm>

          <Textarea
            id="post-content"
            name="content"
            className="min-h-32"
            onChange={(e) => setData("content", e.target.value)}
            minLength={10}
            placeholder="Escribe lo que quieres compartir"
            required
            value={form.data.content}
          />

          <InputError message={errors.content} />
        </div>

        <div className="flex flex-col gap-2">
          <LabelForm error={errors.image_file} htmlFor="post-image">
            Imagen
          </LabelForm>

          <Input
            id="post-image"
            name="image_file"
            type="file"
            onChange={(e) => setData("image_file", e.target.files?.[0])}
            accept="image/*"
          />

          <InputError message={errors.image_file} />
        </div>
      </>
    </form>
  );
}

export default PostForm;
