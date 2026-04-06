import { IoIosNotificationsOutline } from "react-icons/io";

import { Tooltip, TooltipContent, TooltipTrigger } from "@/components/ui/tooltip";

function DashboardNotificationsHeader() {
  return (
    <>
      <Tooltip>
        <TooltipTrigger asChild>
          <IoIosNotificationsOutline className="h-8 w-8 cursor-pointer text-gray-600" />
        </TooltipTrigger>

        <TooltipContent>Notificaciones</TooltipContent>
      </Tooltip>
    </>
  );
}

export default DashboardNotificationsHeader;
