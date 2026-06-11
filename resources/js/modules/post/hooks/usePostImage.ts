import type {
  ChangeEvent,
  Dispatch,
  DragEvent,
  MouseEvent,
  RefObject,
  SetStateAction,
} from "react";
import { useRef, useState } from "react";

import type { SetDataAction } from "@inertiajs/react";

import type { PostFormData } from "../types/post";

interface UsePostImageProps {
  setData: SetDataAction<PostFormData>;
}

interface UsePostImageReturn {
  imageUrl: string | undefined;
  isHoverImage: boolean;
  fileInputRef: RefObject<HTMLInputElement | null>;
  isLoadingImage: boolean;
  handleImageBoxClick: () => void;
  handleImageChange: (event: ChangeEvent<HTMLInputElement>) => void;
  handleRemoveImage: (event: MouseEvent<HTMLButtonElement>) => void;
  handleUpdateImage: (event: MouseEvent<HTMLButtonElement>) => void;
  handleImageDrop: (event: DragEvent<HTMLDivElement>) => void;
  handleImageLoad: () => void;
  handleImageError: () => void;
  setIsHoverImage: Dispatch<SetStateAction<boolean>>;
  setIsLoadingImage: Dispatch<SetStateAction<boolean>>;
}

export function usePostImage({ setData }: UsePostImageProps): UsePostImageReturn {
  const [imageUrl, setImageUrl] = useState<string | undefined>(undefined);
  const [isHoverImage, setIsHoverImage] = useState<boolean>(false);
  const [isLoadingImage, setIsLoadingImage] = useState<boolean>(false);

  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const setImageFile = (file?: File): void => {
    setImageUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev);

      return file ? URL.createObjectURL(file) : undefined;
    });
  };

  const updateImage = (file?: File): void => {
    setData("image_file", file);
    setImageFile(file);
    setIsLoadingImage(Boolean(file));
  };

  const handleImageBoxClick = (): void => fileInputRef.current?.click();

  const handleImageChange = (event: ChangeEvent<HTMLInputElement>): void => {
    const file = event.currentTarget.files?.[0];

    updateImage(file);
  };

  const handleUpdateImage = (event: MouseEvent<HTMLButtonElement>): void => {
    event.stopPropagation();
    fileInputRef.current?.click();
  };

  const handleRemoveImage = (event: MouseEvent<HTMLButtonElement>): void => {
    event.stopPropagation();

    updateImage(undefined);

    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  const handleImageDrop = (event: DragEvent<HTMLDivElement>): void => {
    event.preventDefault();
    event.stopPropagation();

    const file = event.dataTransfer.files?.[0];

    updateImage(file);
    setIsHoverImage(false);

    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  const handleImageLoad = (): void => setIsLoadingImage(false);

  const handleImageError = (): void => setIsLoadingImage(false);

  return {
    imageUrl,
    isHoverImage,
    fileInputRef,
    handleImageError,
    handleImageLoad,
    isLoadingImage,
    handleImageDrop,
    handleImageBoxClick,
    handleImageChange,
    handleRemoveImage,
    handleUpdateImage,
    setIsHoverImage,
    setIsLoadingImage,
  };
}
