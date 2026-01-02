import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip'
import React from 'react'
import { IoIosNotificationsOutline } from "react-icons/io";


function NotificationsHeader() {
  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          <IoIosNotificationsOutline className='w-8 h-8 cursor-pointer text-gray-600' />
        </TooltipTrigger>

        <TooltipContent>
          Notificaciones
        </TooltipContent>
      </Tooltip>
    </>
  )
}

export default NotificationsHeader;
