import { LuUserRoundCog } from "react-icons/lu";
import { MdLockOutline, MdOutlineRoomPreferences, MdOutlineSecurity } from "react-icons/md";

import { Button } from "@/components/ui/button";

function SettingSidebar() {
  return (
    <aside className="flex h-full w-60 flex-col items-center border-r">
      <nav className="w-full pr-2">
        <ul className="space-y-4">
          <li>
            <Button variant="ghost" className="w-full justify-start">
              <LuUserRoundCog className="mr-2 inline-block" />
              Perfil
            </Button>
          </li>
          <li>
            <Button variant="ghost" className="w-full justify-start">
              <MdLockOutline className="mr-2 inline-block" />
              Contraseña
            </Button>
          </li>
          <li>
            <Button variant="ghost" className="w-full justify-start">
              <MdOutlineSecurity className="mr-2 inline-block" />
              Two Factor Authentication
            </Button>
          </li>
          <li>
            <Button variant="ghost" className="w-full justify-start">
              <MdOutlineRoomPreferences className="mr-2 inline-block" />
              Preferencias
            </Button>
          </li>
        </ul>
      </nav>
    </aside>
  );
}

export default SettingSidebar;
