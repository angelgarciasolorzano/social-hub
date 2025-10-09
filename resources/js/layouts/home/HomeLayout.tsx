import { HeaderHome } from '@/components/header';
import { SidebarLeft, SidebarRight } from '@/components/sidebar';
import { usePage, router } from '@inertiajs/react';
import { PropsWithChildren, useEffect } from 'react'
import { toast } from 'sonner';

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

  useEffect(() => {
    if (notification) {
      toast(notification.message, {
        action: notification.action && {
          label: notification.action.label,
          onClick: () => router.visit(notification.action!.url)
        }
      })
    }
  }, [notification]);

  return (
    <div className='flex flex-col h-screen'>
        <HeaderHome />

        <div className='flex flex-1 overflow-hidden'>
            <SidebarLeft />
            <div className='flex-1 flex flex-col gap-4 overflow-y-auto'>
                {children}
            </div>
            <SidebarRight />
        </div>
    </div>
  )
}

export default HomeLayout;