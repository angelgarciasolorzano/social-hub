import { Header, SidebarLeft, SidebarRight } from '@/components';
import { usePage, router } from '@inertiajs/react';
import { PropsWithChildren } from 'react'
import { toast } from 'sonner';
//import { toast, Toaster } from 'sonner';

function HomeLayout({ children }: PropsWithChildren) {
  const {props} = usePage();

  const notification = props.notification as {
    type: 'success' | 'error',
    message: string,
    action?: {
      label: string,
      url: string
    }
  } | null
  console.log(props);

  if (notification) {
    toast(notification.message, {
      action: notification.action && {
        label: notification.action.label,
        onClick: () => router.visit(notification.action!.url)
      }
    })
  }

  return (
    <div className='flex flex-col h-screen'>
        <Header />
        <div className='flex flex-1'>
            <SidebarLeft />
            <div className='flex-1 flex flex-col gap-4'>
                {children}
            </div>
            <SidebarRight />
        </div>
    </div>
  )
}

export default HomeLayout;