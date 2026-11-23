import type { JSX } from "react";
import { useState } from "react";

import { MenuIcon, XIcon } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";
import {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/shared/components/shadcn/ui/sheet";

import { useTranslation } from "@/shared/hooks";

import { HomeNavigationList } from "./HomeSidebarLeft";

function HomeMobileNavigation(): JSX.Element {
  const { t } = useTranslation();

  const [isOpen, setIsOpen] = useState<boolean>(false);

  return (
    <Sheet onOpenChange={setIsOpen} open={isOpen}>
      <SheetTrigger asChild>
        <Button
          aria-label={t("home/sidebar.mobile.open")}
          className="lg:hidden"
          size="icon"
          type="button"
          variant="ghost"
        >
          <MenuIcon aria-hidden="true" className="size-5" />
        </Button>
      </SheetTrigger>

      <SheetContent className="w-72 gap-2 px-3 py-4" showCloseButton={false} side="left">
        <SheetHeader className="flex-row items-center justify-between p-0 px-3">
          <SheetTitle>{t("home/sidebar.mobile.title")}</SheetTitle>

          <SheetDescription className="sr-only">
            {t("home/sidebar.navigation.label")}
          </SheetDescription>

          <SheetClose asChild>
            <Button
              aria-label={t("home/sidebar.mobile.close")}
              size="icon-sm"
              type="button"
              variant="ghost"
            >
              <XIcon aria-hidden="true" />
            </Button>
          </SheetClose>
        </SheetHeader>

        <nav aria-label={t("home/sidebar.navigation.label")}>
          <HomeNavigationList
            onNavigate={() => {
              setIsOpen(false);
            }}
          />
        </nav>
      </SheetContent>
    </Sheet>
  );
}

export default HomeMobileNavigation;
