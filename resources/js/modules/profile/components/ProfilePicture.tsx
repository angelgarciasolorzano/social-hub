import { RiImageEditLine } from "react-icons/ri";

import { UserImageType } from "../enums/userImageType";
import { useUserImageUpload } from "../hooks/useUserImageUpload";
import ProfileImageUploadModal from "./modals/ProfileImageUploadDialog";

interface ProfilePictureProps {
  profilePicture: string;
}

function ProfilePicture({ profilePicture }: ProfilePictureProps) {
  const {
    isHover,
    isModalOpen,
    imageUrl,
    onClose,
    setIsHover,
    handleFileUpload,
    handleImageUpload,
  } = useUserImageUpload({
    typeImage: UserImageType.PROFILE_PICTURE,
  });

  return (
    <div
      className="absolute -bottom-10 left-5 z-50 h-24 w-24 rounded-full"
      onMouseEnter={() => setIsHover(true)}
      onMouseLeave={() => setIsHover(false)}
    >
      <img
        className="h-full w-full rounded-full border border-white object-cover shadow-md dark:border-gray-600"
        alt="Foto de perfil"
        src={profilePicture}
      />

      {isHover && (
        <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/40">
          <label
            className="cursor-pointer text-white/70 hover:text-white"
            htmlFor="profile-picture-upload"
          >
            <RiImageEditLine className="h-9 w-9" />
          </label>
        </div>
      )}

      <input
        id="profile-picture-upload"
        type="file"
        className="hidden"
        onChange={handleFileUpload}
        accept="image/*"
      />

      {isModalOpen && imageUrl && (
        <ProfileImageUploadModal
          type="profile"
          onClose={onClose}
          onConfirm={handleImageUpload}
          preview={imageUrl}
        />
      )}
    </div>
  );
}

export default ProfilePicture;
