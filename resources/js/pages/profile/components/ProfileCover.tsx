import { RiImageEditLine } from "react-icons/ri";

import { UserImageType } from "../enums/userImageType";
import { useUserImageUpload } from "../hooks/useUserImageUpload";
import ProfileImageUploadModal from "./modals/ProfileImageUploadModal";

interface ProfileCoverProps {
  coverImage: string;
}

function ProfileCover({ coverImage }: ProfileCoverProps) {
  const {
    isHover,
    isModalOpen,
    imageUrl,
    onClose,
    setIsHover,
    handleFileUpload,
    handleImageUpload,
  } = useUserImageUpload({ typeImage: UserImageType.COVER_IMAGE });

  return (
    <div
      className="h-[350px] w-full dark:border-gray-600"
      onMouseEnter={() => setIsHover(true)}
      onMouseLeave={() => setIsHover(false)}
    >
      <img
        className="h-full w-full rounded-md object-cover"
        alt="Foto de portada"
        src={coverImage ?? "https://picsum.photos/200"}
      />

      {isHover && (
        <div className="absolute inset-0 flex items-center justify-center rounded-md bg-black/40">
          <label
            className="cursor-pointer text-white/70 hover:text-white"
            htmlFor="profile-cover-upload"
          >
            <RiImageEditLine className="h-24 w-24" />
          </label>
        </div>
      )}

      <input
        id="profile-cover-upload"
        type="file"
        className="hidden"
        onChange={handleFileUpload}
        accept="image/*"
      />

      {isModalOpen && imageUrl && (
        <ProfileImageUploadModal
          type="cover"
          onClose={onClose}
          onConfirm={handleImageUpload}
          preview={imageUrl}
        />
      )}
    </div>
  );
}

export default ProfileCover;
