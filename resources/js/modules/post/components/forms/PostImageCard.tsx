import type { Dispatch, FormEvent, MouseEvent, RefObject } from "react";

import { IoCloudUploadOutline } from "react-icons/io5";

import { Button } from "@/shared/components/ui/button";

import type { Appearance } from "@/shared/hooks";

import { cn } from "@/shared/lib";

interface PostImageCardProps {
  appearance: Appearance;
  imageUrl?: string;
  isHoverImage: boolean;
  handleImageBoxClick: () => void;
  handleUpdateImage: (event: MouseEvent<HTMLButtonElement>) => void;
  handleRemoveImage: (event: MouseEvent<HTMLButtonElement>) => void;
  fileInputRef: RefObject<HTMLInputElement | null>;
  handleImageChange: (event: FormEvent<HTMLInputElement>) => void;
  setIsHoverImage: Dispatch<React.SetStateAction<boolean>>;
}

function PostImageCard(props: PostImageCardProps) {
  const {
    handleRemoveImage,
    fileInputRef,
    handleImageChange,
    handleUpdateImage,
    handleImageBoxClick,
    setIsHoverImage,
    isHoverImage,
    imageUrl,
    appearance,
  } = props;

  return (
    <div
      aria-label="Agregar imagen a la publicación"
      className={cn(
        "flex min-h-64 flex-col items-center justify-center rounded-md border border-dashed p-4",
        !imageUrl && isHoverImage && "cursor-pointer hover:bg-gray-50 dark:hover:bg-[#1D1D1D]/30",
        "dark:border-[#343434]",
      )}
      onClick={handleImageBoxClick}
      onMouseEnter={() => setIsHoverImage(true)}
      onMouseLeave={() => setIsHoverImage(false)}
      role="button"
    >
      <div className="items-center">
        {imageUrl ? (
          <div className="relative h-full w-full">
            <img
              className="h-full w-full rounded-md object-cover"
              alt="Vista previa de la imagen seleccionada"
              src={imageUrl}
            />

            {isHoverImage && (
              <div
                className={cn(
                  "absolute inset-0 flex flex-col items-center justify-center gap-4 rounded-md transition-opacity",
                  "bg-black/80 dark:bg-black/90",
                )}
              >
                <span className="text-sm font-medium text-white">
                  ¿Desea actualizar la imagen o eliminarla?
                </span>

                <div className="flex items-center gap-4">
                  <Button
                    className="cursor-pointer"
                    onClick={handleUpdateImage}
                    variant={appearance === "light" ? "outline" : "default"}
                  >
                    Actualizar
                  </Button>

                  <Button
                    className={cn(
                      "cursor-pointer",
                      "dark:bg-red-700 dark:text-white dark:hover:bg-red-800",
                    )}
                    onClick={handleRemoveImage}
                    variant={appearance === "light" ? "destructive" : null}
                  >
                    Eliminar
                  </Button>
                </div>
              </div>
            )}
          </div>
        ) : (
          <div className="flex flex-col items-center gap-2">
            <IoCloudUploadOutline className="h-6 w-6 text-muted-foreground" />

            <p className="text-sm font-medium dark:text-white">
              Seleccione un archivo o arrástrelo y suéltelo aquí.
            </p>

            <span className="text-xs dark:text-white/70">
              JPG, PNG, WebP. El archivo no debe superar los 2 MB{" "}
            </span>
          </div>
        )}
      </div>

      <input
        id="post-image"
        name="image_file"
        type="file"
        className="hidden"
        onChange={handleImageChange}
        accept="image/*"
        ref={fileInputRef}
        tabIndex={-1}
      />
    </div>
  );
}

export default PostImageCard;
