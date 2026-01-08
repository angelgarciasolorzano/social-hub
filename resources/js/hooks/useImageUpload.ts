import { useState, ChangeEvent } from 'react';

interface UseImageUploadReturn {
  isHover: boolean;
  isModalOpen: boolean;
  imageFile: File | null;
  imageUrl: string | null;
  setIsHover: (hover: boolean) => void;
  handleFileUpload: (event: ChangeEvent<HTMLInputElement>) => void;
  onClose: () => void;
}

export function useImageUpload(): UseImageUploadReturn {
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

  return {
    isHover,
    isModalOpen,
    imageFile,
    imageUrl,
    setIsHover,
    handleFileUpload,
    onClose,
  };
}