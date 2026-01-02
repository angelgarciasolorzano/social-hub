import { RiImageEditLine } from "react-icons/ri";
import ProfileImageUploadModal from './modals/ProfileImageUploadModal';
import { router } from '@inertiajs/react';
import { toast } from 'sonner';
import { useImageUpload } from '@/hooks/useImageUpload';
import ProfileController from "@/actions/App/Http/Controllers/user/ProfileController";

interface ProfilePictureProps {
  profilePicture?: string;
};

function ProfilePicture({ profilePicture }: ProfilePictureProps) {
  const {
    isHover, isModalOpen, imageFile, imageUrl, 
    onClose, setIsHover, handleFileUpload
  } = useImageUpload();
  
  const handleImageUpload = () => {
    if (!imageFile) return;

    router.post(ProfileController.updatedProfilePicture.url(), {
      profile_picture: imageFile
    }, {
      onSuccess: () => {
        onClose();
        router.reload({
          only: ['user'],
        })
      },
      onError: (error) => {
        toast.error(error.profile_picture);
      }
    })
  };

  return (
    <div
      onMouseEnter={() => setIsHover(true)}
      onMouseLeave={() => setIsHover(false)}
      className='absolute w-24 h-24 rounded-full left-5 -bottom-10 z-50'
    > 
      <img
        src={profilePicture ?? 'https://picsum.photos/200'}
        alt='Foto de perfil'
        className='w-full h-full rounded-full border border-white shadow-md object-cover dark:border-gray-600'
      />

      {isHover && (
        <div className='absolute inset-0 rounded-full flex items-center justify-center bg-black/40'>
          <label htmlFor='profile-picture-upload' className='cursor-pointer text-white/70 hover:text-white'>
            <RiImageEditLine className='w-9 h-9' />
          </label>
        </div>
      )}

      <input
        id='profile-picture-upload'
        type='file'
        accept='image/*'
        className='hidden'
        onChange={handleFileUpload}
      />

      {isModalOpen && imageUrl && (
        <ProfileImageUploadModal
          type="profile"
          preview={imageUrl} 
          onClose={onClose} 
          onConfirm={handleImageUpload} 
        />
      )}
    </div>
  )
}

export default ProfilePicture;
