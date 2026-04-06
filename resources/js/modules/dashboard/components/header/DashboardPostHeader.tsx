import type { Dispatch, SetStateAction } from "react";

import { HiOutlinePlus } from "react-icons/hi2";

import { Publication } from "@/features/publication";

import { Tooltip, TooltipContent, TooltipTrigger } from "@/shared/components/ui/tooltip";

interface PublicationHeaderProps {
  isOpenModal: boolean;
  setIsOpenModal: Dispatch<SetStateAction<boolean>>;
}

function DashboardPostHeader({ isOpenModal, setIsOpenModal }: PublicationHeaderProps) {
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

      <Publication open={isOpenModal} setOpen={setIsOpenModal} />
    </>
  );
}

export default DashboardPostHeader;
