import PublicationForm from '@/components/publication/PublicationForm';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import React, { Dispatch, SetStateAction } from 'react'
import { HiOutlinePlus } from "react-icons/hi2";

interface PublicationHeaderProps {
  isOpenModal: boolean;
  setIsOpenModal: Dispatch<SetStateAction<boolean>>;
};

function PublicationHeader({ isOpenModal, setIsOpenModal }: PublicationHeaderProps) {
  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          <div className='border rounded-md border-gray-200 dark:border-gray-700'>
            <HiOutlinePlus
              className='h-8 w-8 cursor-pointer text-gray-600 dark:text-gray-200'
              onClick={() => setIsOpenModal(true)}
            />
          </div>
        </TooltipTrigger>

        <TooltipContent>Crear publicación</TooltipContent>
      </Tooltip>

      <PublicationForm open={isOpenModal} setOpen={setIsOpenModal} />
    </>
  )
}

export default PublicationHeader
