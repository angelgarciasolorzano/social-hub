import type { ChangeEvent } from "react";
import { useState } from "react";

import { router } from "@inertiajs/react";

import { toast } from "sonner";

import { updateImage } from "@/shared/wayfinder/actions/App/User/Controllers/UserController";

import { UserImageType } from "../enums/userImageType";

interface UpdateImageParams {
  typeImage: UserImageType;
}

interface UseImageUploadReturn {
  handleFileUpload: (event: ChangeEvent<HTMLInputElement>) => void;
  handleImageUpload: () => void;
  imageFile: File | null;
  imageUrl: string | null;
  isHover: boolean;
  isModalOpen: boolean;
  onClose: () => void;
  setIsHover: (hover: boolean) => void;
}

export function useUserImageUpload({
  typeImage = UserImageType.PROFILE_PICTURE,
}: UpdateImageParams): UseImageUploadReturn {
  const [isHover, setIsHover] = useState(false);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [imageFile, setImageFile] = useState<File | null>(null);
  const [imageUrl, setImageUrl] = useState<string | null>(null);

  const handleFileUpload = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (file) {
      setImageFile(file);
      setImageUrl(URL.createObjectURL(file));
      setIsModalOpen(true);
    }
  };

  const onClose = () => {
    setIsModalOpen(false);
    setImageUrl(null);
    setImageFile(null);
    setIsHover(false);
  };

  const handleImageUpload = () => {
    if (!imageFile) return;

    router.post(
      updateImage.url({ type: typeImage }),
      {
        [typeImage]: imageFile,
      },
      {
        onSuccess: () => {
          onClose();
          router.reload({
            only: ["user"],
          });
        },
        onError: (error) => {
          toast.error(error[typeImage]);
        },
      },
    );
  };

  return {
    isHover,
    isModalOpen,
    imageFile,
    imageUrl,
    setIsHover,
    handleFileUpload,
    onClose,
    handleImageUpload,
  };
}
