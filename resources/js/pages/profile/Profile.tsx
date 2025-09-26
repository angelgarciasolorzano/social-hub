import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react'

import HomeLayout from '@/layouts/home/home-layout'

function Profile() {
    const { auth } = usePage<SharedData>().props;

    return (
        <HomeLayout>
            <div className='relative py-3 px-4'>
                <div className='w-full h-[300px] bg-amber-300 rounded-md'></div>

                <div className='absolute left-10 -bottom-12 z-50'>
                    <div className='rounded-full w-24 h-24 bg-red-400'></div>
                    <h4>{auth.user.name}</h4>
                </div>
            </div>

            <div className='pt-16 px-4'>
                Lorem ipsum dolor sit amet consectetur adipisicing elit. Dicta sunt molestiae dolore facere suscipit, minima explicabo veniam fugiat nostrum quas?
            </div>
        </HomeLayout>
    )
}

export default Profile
