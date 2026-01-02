import {IconType} from 'react-icons';

import { FiHome } from 'react-icons/fi';
import { FaRegUser } from 'react-icons/fa6';
import { MdOutlineLogout } from 'react-icons/md';
import { PiNutBold } from 'react-icons/pi';

import profile from '@/routes/profile';

import { home, logout } from '@/routes';

import { RouteDefinition } from '@/wayfinder';

interface MenuItem {
  text: string;
  url?: string | RouteDefinition<'get' | 'post'>;
  method?: 'post' | 'get';
  action?: 'openModal';
  icon: IconType;
}

export const menuItems: MenuItem[] = [
  {
    text: 'Inicio',
    url: home.url(),
    icon: FiHome,
  },
  {
    text: 'Perfil',
    url: profile.index(),
    icon: FaRegUser,
  },
  {
    text: 'Configuración',
    action: 'openModal',
    icon: PiNutBold,
  },
  {
    text: 'Cerrar Sesión',
    url: logout(),
    method: 'post',
    icon: MdOutlineLogout,
  },
];