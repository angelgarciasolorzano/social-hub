import { IoIosNotificationsOutline } from "react-icons/io";


import { useModal} from "@/hooks/useModal";
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';

import { SharedData } from '@/types';

import { useEchoNotification } from "@laravel/echo-react";
import { toast } from 'sonner';
import Profile from './profile/ProfileHeader'
import { usePage } from "@inertiajs/react";
import SearchHeader from "./search/SearchHeader";
import PublicationHeader from "./PublicationHeader";


function HomeHeader() {
  const { auth } = usePage<SharedData>().props;

  useEchoNotification(
      `App.Models.User.${auth.user.id}`,
      (notification) => {
          console.log(notification);
          //alert(notification.type);
          toast.info("Te llego una solicitud de amistad");
      },
  );


  return (
    <div className='flex items-center justify-between border-b border-gray-200 px-3 py-3.5'>
      <div>
        <h1 className='text-blue-500 font-bold text-2xl'>SOCIAL HUB</h1>
      </div>

      <HeaderAction />

      {/* <div className="relative">
      <IoIosNotificationsOutline className="w-8 h-8 cursor-pointer text-gray-600" />
      <div className="absolute top-10 right-0 w-64 bg-white shadow-md rounded-md max-h-80 overflow-y-auto">
        {notifications.length > 0 ? (
          notifications.map((n, i) => (
            <div key={i} className="p-2 border-b text-sm">
              <strong>{n.requester_name}</strong> te envió una solicitud
            </div>
          ))
        ) : (
          <p className="p-2 text-sm text-gray-500">Sin notificaciones</p>
        )}
      </div>
    </div> */}
    </div>
  )
}

function HeaderAction() {
  const { open: isOpenModal, setOpen: setIsOpenModal } = useModal();

  return (
    <div className='flex items-center gap-4'>
      <SearchHeader />
      <PublicationHeader isOpenModal={isOpenModal} setIsOpenModal={setIsOpenModal} />
      <HeaderActionNotifications />
      {/* <HeaderActionProfile /> */}

      <Profile  />
    </div>
  )
};

function HeaderActionNotifications() {
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
};

export default HomeHeader;
