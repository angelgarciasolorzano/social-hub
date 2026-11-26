import { useState } from "react";

import { useHttp } from "@inertiajs/react";

import { useTranslation } from "@/shared/hooks";

import type {
  FriendshipAction,
  FriendshipActionResponse,
  FriendshipState,
} from "../../types/friendshipSearch";
import {
  friendshipActionUrl,
  optimisticFriendshipState,
  parseFriendshipActionResponse,
  processingLabelKey,
} from "../../utils/friendshipAction";

interface FriendshipStatus {
  friendshipId: null | number;
  friendshipState: FriendshipState;
}

interface UseFriendshipButtonOptions {
  friendshipId: null | number;
  friendshipState: FriendshipState;
  onChange?: (friendshipState: FriendshipState, friendshipId: null | number) => void;
  userId: number;
  userName: string;
}

interface UseFriendshipButtonReturn {
  friendshipState: FriendshipState;
  isProcessing: boolean;
  isRemoveDialogOpen: boolean;
  pendingAction: FriendshipAction | null;
  processingLabel: (friendshipAction: FriendshipAction) => string | undefined;
  requestError: null | string;
  runAction: (friendshipAction: FriendshipAction) => void;
  setIsRemoveDialogOpen: (isOpen: boolean) => void;
}

/**
 * The state of a friendship button: it shows the new state at once (optimistic) and then applies the
 * one the server answers. A refusal with a body (a race with the other person) applies the stored
 * state and shows its message; any other failure restores the previous state with a generic error.
 */
export function useFriendshipButton({
  friendshipId: initialFriendshipId,
  friendshipState: initialFriendshipState,
  onChange,
  userId,
  userName,
}: UseFriendshipButtonOptions): UseFriendshipButtonReturn {
  const { t } = useTranslation();

  const [friendshipStatus, setFriendshipStatus] = useState<FriendshipStatus>({
    friendshipId: initialFriendshipId,
    friendshipState: initialFriendshipState,
  });

  const [pendingAction, setPendingAction] = useState<FriendshipAction | null>(null);

  const [requestError, setRequestError] = useState<null | string>(null);

  const [isRemoveDialogOpen, setIsRemoveDialogOpen] = useState<boolean>(false);

  const friendshipRequest = useHttp<Record<string, never>, FriendshipActionResponse>(() => ({}));

  const applyServerStatus = (friendshipActionResponse: FriendshipActionResponse): void => {
    setFriendshipStatus({
      friendshipId: friendshipActionResponse.friendshipId,
      friendshipState: friendshipActionResponse.state,
    });

    onChange?.(friendshipActionResponse.state, friendshipActionResponse.friendshipId);
  };

  const runAction = (friendshipAction: FriendshipAction): void => {
    if (friendshipRequest.processing) return;

    const actionUrl = friendshipActionUrl(friendshipAction, userId, friendshipStatus.friendshipId);

    if (actionUrl === null) {
      setRequestError(t("friendship/button.error"));

      return;
    }

    const previousStatus = friendshipStatus;

    const revert = (): void => {
      setFriendshipStatus(previousStatus);
      setRequestError(t("friendship/button.error"));
    };

    setRequestError(null);
    setPendingAction(friendshipAction);
    setFriendshipStatus({
      friendshipId: friendshipStatus.friendshipId,
      friendshipState: optimisticFriendshipState(friendshipAction),
    });

    const send =
      friendshipAction === "send" || friendshipAction === "accept"
        ? friendshipRequest.post
        : friendshipRequest.delete;

    send(actionUrl, {
      onSuccess: (friendshipActionResponse) => {
        applyServerStatus(friendshipActionResponse);
        setIsRemoveDialogOpen(false);
      },
      onHttpException: (response) => {
        const friendshipActionResponse = parseFriendshipActionResponse(response.data);

        if (friendshipActionResponse === null) {
          revert();
        } else {
          applyServerStatus(friendshipActionResponse);
          setRequestError(friendshipActionResponse.message);
        }

        setIsRemoveDialogOpen(false);

        return false;
      },
      onNetworkError: () => {
        revert();

        return false;
      },
      onFinish: () => {
        setPendingAction(null);
      },
    }).catch(() => undefined);
  };

  const processingLabel = (friendshipAction: FriendshipAction): string | undefined =>
    pendingAction === friendshipAction
      ? t(processingLabelKey(friendshipAction), { name: userName })
      : undefined;

  return {
    friendshipState: friendshipStatus.friendshipState,
    isProcessing: friendshipRequest.processing,
    isRemoveDialogOpen,
    pendingAction,
    processingLabel,
    requestError,
    runAction,
    setIsRemoveDialogOpen,
  };
}
