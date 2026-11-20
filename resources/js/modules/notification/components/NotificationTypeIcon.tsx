import type { JSX } from "react";

import type { LucideIcon } from "lucide-react";
import { HeartIcon, MessageCircleIcon, ReplyIcon, UserCheckIcon, UserPlusIcon } from "lucide-react";

import { useTranslation } from "@/shared/hooks";

import { cn } from "@/shared/lib/utils";

import type { NotificationType } from "../types/notification";

const ICON_OF_NOTIFICATION_TYPE: Record<NotificationType, LucideIcon> = {
  comment_on_post: MessageCircleIcon,
  comment_reply: ReplyIcon,
  likes_grouped: HeartIcon,
  friend_request_received: UserPlusIcon,
  friend_request_accepted: UserCheckIcon,
};

interface NotificationTypeIconProps {
  className?: string;
  type: NotificationType;
}

function NotificationTypeIcon(props: NotificationTypeIconProps): JSX.Element {
  const { className, type } = props;

  const { t } = useTranslation();

  const Icon = ICON_OF_NOTIFICATION_TYPE[type];

  return (
    <span
      aria-label={t(`notification/center.types.${type}`)}
      className={cn("inline-flex size-4 shrink-0 text-muted-foreground", className)}
      role="img"
    >
      <Icon aria-hidden="true" className="size-4" />
    </span>
  );
}

export default NotificationTypeIcon;
