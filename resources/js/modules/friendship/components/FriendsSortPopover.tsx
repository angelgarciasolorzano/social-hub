import type { JSX } from "react";
import { useId } from "react";

import { ArrowDownNarrowWide, RotateCcw } from "lucide-react";

import { Button } from "@/shared/components/shadcn/ui/button";
import { Popover, PopoverContent, PopoverTrigger } from "@/shared/components/shadcn/ui/popover";
import { RadioGroup, RadioGroupItem } from "@/shared/components/shadcn/ui/radio-group";
import { Separator } from "@/shared/components/shadcn/ui/separator";

import { useTranslation } from "@/shared/hooks";

import type { FriendshipListSort } from "../types/friendshipList";

const FRIENDSHIP_LIST_SORTS: FriendshipListSort[] = ["recent", "name"];

const DEFAULT_FRIENDSHIP_LIST_SORT: FriendshipListSort = "recent";

interface FriendsSortPopoverProps {
  onSortChange: (sort: FriendshipListSort) => void;
  sort: FriendshipListSort;
}

function FriendsSortPopover(props: FriendsSortPopoverProps): JSX.Element {
  const { onSortChange, sort } = props;

  const { t } = useTranslation();

  const idPrefix = useId();

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button
          aria-label={`${t("friendship/friends.sort.label")}: ${t(`friendship/friends.sort.${sort}`)}`}
          className="shrink-0"
          type="button"
          variant="outline"
        >
          <ArrowDownNarrowWide aria-hidden="true" />

          {t(`friendship/friends.sort.${sort}`)}
        </Button>
      </PopoverTrigger>

      <PopoverContent align="end" className="w-80 p-3">
        <div className="flex flex-col gap-3">
          <h4 className="text-sm font-medium" id={`${idPrefix}-title`}>
            {t("friendship/friends.sort.title")}
          </h4>

          <RadioGroup
            aria-labelledby={`${idPrefix}-title`}
            className="gap-1"
            onValueChange={(value) => {
              onSortChange(value as FriendshipListSort);
            }}
            value={sort}
          >
            {FRIENDSHIP_LIST_SORTS.map((friendshipListSort) => (
              <label
                className="flex cursor-pointer items-start gap-3 rounded-md p-2 transition-colors hover:bg-accent/50 has-data-[state=checked]:bg-blue-100/50 has-data-[state=checked]:dark:bg-blue-950/20"
                htmlFor={`${idPrefix}-${friendshipListSort}`}
                key={friendshipListSort}
              >
                <RadioGroupItem
                  className="mt-0.5"
                  id={`${idPrefix}-${friendshipListSort}`}
                  value={friendshipListSort}
                />

                <div className="flex flex-1 flex-col gap-0.5 leading-tight">
                  <span className="text-sm font-medium">
                    {t(`friendship/friends.sort.${friendshipListSort}`)}
                  </span>

                  <span className="text-xs font-normal text-muted-foreground">
                    {t(`friendship/friends.sort.${friendshipListSort}_description`)}
                  </span>
                </div>
              </label>
            ))}
          </RadioGroup>

          <Separator />

          <div className="flex items-center justify-between">
            <div className="flex flex-col leading-tight">
              <span className="text-sm font-medium">{t("friendship/friends.sort.default")}</span>

              <span className="text-xs font-normal text-muted-foreground">
                {t(`friendship/friends.sort.${DEFAULT_FRIENDSHIP_LIST_SORT}`)}
              </span>
            </div>

            <Button
              disabled={sort === DEFAULT_FRIENDSHIP_LIST_SORT}
              onClick={() => {
                onSortChange(DEFAULT_FRIENDSHIP_LIST_SORT);
              }}
              size="sm"
              type="button"
              variant="ghost"
            >
              <RotateCcw aria-hidden="true" />

              {t("friendship/friends.sort.reset")}
            </Button>
          </div>
        </div>
      </PopoverContent>
    </Popover>
  );
}

export default FriendsSortPopover;
