import type { Dispatch, FormEvent, MouseEvent, RefObject, SetStateAction } from "react";
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
  handleImageBoxClick: () => void;
  handleImageChange: (event: FormEvent<HTMLInputElement>) => void;
  handleRemoveImage: (event: MouseEvent<HTMLButtonElement>) => void;
  handleUpdateImage: (event: MouseEvent<HTMLButtonElement>) => void;
  setIsHoverImage: Dispatch<SetStateAction<boolean>>;
}

export function usePostImage({ setData }: UsePostImageProps): UsePostImageReturn {
  const [imageUrl, setImageUrl] = useState<string | undefined>(undefined);
  const [isHoverImage, setIsHoverImage] = useState<boolean>(false);

  const fileInputRef = useRef<HTMLInputElement | null>(null);

  const handleImageBoxClick = () => fileInputRef.current?.click();

  const setImageFile = (file?: File) => {
    setImageUrl((prev) => {
      if (prev) URL.revokeObjectURL(prev);

      return file ? URL.createObjectURL(file) : undefined;
    });
  };

  const handleImageChange = (event: FormEvent<HTMLInputElement>) => {
    const file = event.currentTarget.files?.[0];

    setData("image_file", file);

    setImageFile(file);
  };

  const handleRemoveImage = (event: MouseEvent<HTMLButtonElement>) => {
    event.stopPropagation();

    setData("image_file", undefined);
    setImageFile(undefined);

    if (fileInputRef.current) fileInputRef.current.value = "";
  };

  const handleUpdateImage = (event: MouseEvent<HTMLButtonElement>) => {
    event.stopPropagation();
    fileInputRef.current?.click();
  };

  return {
    imageUrl,
    isHoverImage,
    fileInputRef,
    handleImageBoxClick,
    handleImageChange,
    handleRemoveImage,
    handleUpdateImage,
    setIsHoverImage,
  };
}
