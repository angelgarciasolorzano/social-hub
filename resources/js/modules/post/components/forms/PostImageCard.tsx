import type {
  ChangeEvent,
  Dispatch,
  DragEvent,
  DragEventHandler,
  MouseEvent,
  RefObject,
} from "react";

import { IoCloudUploadOutline } from "react-icons/io5";

import { Loader2Icon } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";

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
  handleImageChange: (event: ChangeEvent<HTMLInputElement>) => void;
  handleImageDrop: (event: DragEvent<HTMLDivElement>) => void;
  setIsHoverImage: Dispatch<React.SetStateAction<boolean>>;
  isLoadingImage: boolean;
  handleImageLoad: () => void;
  handleImageError: () => void;
}

function PostImageCard(props: PostImageCardProps) {
  const {
    handleRemoveImage,
    fileInputRef,
    handleImageChange,
    handleUpdateImage,
    handleImageBoxClick,
    handleImageDrop,
    setIsHoverImage,
    isHoverImage,
    imageUrl,
    appearance,
    handleImageLoad,
    handleImageError,
    isLoadingImage,
  } = props;

  const handleDragOver: DragEventHandler<HTMLDivElement> = (event) => {
    event.preventDefault();
    setIsHoverImage(true);
  };

  const handleDragLeave: DragEventHandler<HTMLDivElement> = (event) => {
    event.preventDefault();
    setIsHoverImage(false);
  };

  const handleCardClick = (): void => {
    if (imageUrl) return;

    handleImageBoxClick();
  };

  return (
    <div
      aria-label="Agregar imagen a la publicación"
      className={cn(
        "flex min-h-64 flex-col items-center justify-center rounded-md border border-dashed p-4",
        !imageUrl && isHoverImage && "cursor-pointer hover:bg-gray-50 dark:hover:bg-[#1D1D1D]/30",
        "dark:border-[#343434]",
      )}
      onClick={handleCardClick}
      onDragLeave={handleDragLeave}
      onDragOver={handleDragOver}
      onDrop={handleImageDrop}
      onMouseEnter={() => setIsHoverImage(true)}
      onMouseLeave={() => setIsHoverImage(false)}
      role="button"
    >
      <div className="items-center">
        {imageUrl ? (
          <ImagePreview
            appearance={appearance}
            handleImageError={handleImageError}
            handleImageLoad={handleImageLoad}
            handleRemoveImage={handleRemoveImage}
            handleUpdateImage={handleUpdateImage}
            imageUrl={imageUrl}
            isHoverImage={isHoverImage}
            isLoadingImage={isLoadingImage}
          />
        ) : (
          <ImageEmptyState />
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

type ImageEmptyStateProps = Omit<
  PostImageCardProps,
  | "fileInputRef"
  | "handleImageChange"
  | "handleImageDrop"
  | "setIsHoverImage"
  | "handleImageBoxClick"
>;

function ImagePreview(props: ImageEmptyStateProps) {
  const {
    appearance,
    isLoadingImage,
    handleImageError,
    handleImageLoad,
    isHoverImage,
    imageUrl,
    handleRemoveImage,
    handleUpdateImage,
  } = props;

  return (
    <div className="relative h-full w-full">
      <img
        className={cn(
          "h-full w-full rounded-md object-cover transition-opacity",
          isLoadingImage ? "dark:opacity-20" : "opacity-100",
        )}
        onError={handleImageError}
        onLoad={handleImageLoad}
        alt="Vista previa de la imagen seleccionada"
        src={imageUrl}
      />

      {isLoadingImage && <ImageLoadingOverlay />}

      {!isLoadingImage && isHoverImage && (
        <ImageHoverOverlay
          appearance={appearance}
          handleRemoveImage={handleRemoveImage}
          handleUpdateImage={handleUpdateImage}
        />
      )}
    </div>
  );
}

function ImageLoadingOverlay() {
  return (
    <div
      className={cn(
        "absolute inset-0 flex flex-col items-center justify-center gap-2 rounded-md",
        "bg-black/80 backdrop-blur-[1px] dark:bg-black/50",
      )}
    >
      <Loader2Icon className="h-6 w-6 animate-spin text-muted-foreground dark:text-white" />

      <span className="text-sm font-medium text-muted-foreground dark:text-white">
        Cargando imagen...
      </span>
    </div>
  );
}

type ImageHoverOverlayProps = Pick<
  PostImageCardProps,
  "appearance" | "handleRemoveImage" | "handleUpdateImage"
>;

function ImageHoverOverlay(props: ImageHoverOverlayProps) {
  const { appearance, handleRemoveImage, handleUpdateImage } = props;

  return (
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
          className={cn("cursor-pointer", "dark:bg-red-700 dark:text-white dark:hover:bg-red-800")}
          onClick={handleRemoveImage}
          variant={appearance === "light" ? "destructive" : null}
        >
          Eliminar
        </Button>
      </div>
    </div>
  );
}

function ImageEmptyState() {
  return (
    <div className="flex flex-col items-center gap-2">
      <div className="rounded-full bg-accent p-2">
        <IoCloudUploadOutline className="h-6 w-6 text-muted-foreground" />
      </div>

      <p className="text-sm font-medium dark:text-white">
        Seleccione un archivo o arrástrelo y suéltelo aquí.
      </p>

      <span className="text-xs dark:text-white/70">
        JPG, PNG, WebP. El archivo no debe superar los 5 MB
      </span>
    </div>
  );
}

export default PostImageCard;
