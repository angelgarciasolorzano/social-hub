import { Header, SidebarLeft, SidebarRight } from '@/components';
import { PropsWithChildren } from 'react'

function HomeLayout({ children }: PropsWithChildren) {
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