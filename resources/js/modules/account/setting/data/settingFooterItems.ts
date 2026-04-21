import type { IconType } from "react-icons";
import { FaFacebookSquare } from "react-icons/fa";
import { FaInstagram } from "react-icons/fa6";
import { ImWhatsapp } from "react-icons/im";
import { RiTwitterXLine } from "react-icons/ri";

interface SettingFooterItems {
  icon: IconType;
}

export const settingFooterItems: SettingFooterItems[] = [
  {
    icon: RiTwitterXLine,
  },
  {
    icon: FaFacebookSquare,
  },
  {
    icon: ImWhatsapp,
  },
  {
    icon: FaInstagram,
  },
];
