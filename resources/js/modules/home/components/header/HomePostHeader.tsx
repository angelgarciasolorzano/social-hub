import type { Dispatch, SetStateAction } from "react";

import { HiOutlinePlus } from "react-icons/hi2";

import PostDialog from "@/modules/post/components/PostDialog";

import { Tooltip, TooltipContent, TooltipTrigger } from "@/shared/components/ui/tooltip";

interface HomePostHeaderProps {
  isOpenModal: boolean;
  setIsOpenModal: Dispatch<SetStateAction<boolean>>;
}

function HomePostHeader({ isOpenModal, setIsOpenModal }: HomePostHeaderProps) {
  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          <div className="rounded-md border border-gray-200 dark:border-gray-700">
            <HiOutlinePlus
              className="h-8 w-8 cursor-pointer text-gray-600 dark:text-gray-200"
              onClick={() => setIsOpenModal(true)}
            />
          </div>
        </TooltipTrigger>

        <TooltipContent>Crear publicación</TooltipContent>
      </Tooltip>

      <PostDialog open={isOpenModal} setOpen={setIsOpenModal} />
    </>
  );
}

export default HomePostHeader;
