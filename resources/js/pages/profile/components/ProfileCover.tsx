import { router } from '@inertiajs/react';
import { RiImageEditLine } from 'react-icons/ri';
import ProfileImageUploadModal from './modals/ProfileImageUploadModal';
import { useImageUpload } from '@/hooks/useImageUpload';
import ProfileController from '@/actions/App/Http/Controllers/user/ProfileController';

interface ProfileCoverProps {
  profilePicture?: string;
};

function ProfileCover({ profilePicture }: ProfileCoverProps) {
  const {
    isHover, isModalOpen, imageFile, imageUrl, 
    onClose, setIsHover, handleFileUpload
  } = useImageUpload();
  
  const handleImageUpload = () => {
    if (!imageFile) return;

    router.post(ProfileController.updatedCoverImage.url(), {
      cover_image: imageFile
    }, {
      onSuccess: () => {
        onClose();
        router.reload({
          only: ['user'],
        })
      }
    })
  };

  return (
    <div
      onMouseEnter={() => setIsHover(true)}
      onMouseLeave={() => setIsHover(false)}
      className='w-full h-[350px] dark:border-gray-600'
    >
      <img
        src={profilePicture ?? 'https://picsum.photos/200'}
        alt='Foto de portada'
        className='w-full h-full object-cover rounded-md'
      />

      {isHover && (
        <div className='absolute inset-0 rounded-md flex items-center justify-center bg-black/40'>
          <label htmlFor='profile-cover-upload' className='cursor-pointer text-white/70 hover:text-white'>
            <RiImageEditLine className='w-24 h-24' />
          </label>
        </div>
      )}

      <input
        id='profile-cover-upload'
        type='file'
        accept='image/*'
        className='hidden'
        onChange={handleFileUpload}
      />

      {isModalOpen && imageUrl && (
        <ProfileImageUploadModal
          type="cover"
          preview={imageUrl} 
          onClose={onClose} 
          onConfirm={handleImageUpload} 
        />
      )}
    </div>
  )
}

export default ProfileCover;