import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { CommentType } from "../enums/commentType";
import type { CommentTypeValues } from "../enums/commentType";
import type { CommentsLoadFailure, PaginatedComments } from "../types/comment";

type CommentRequestKind = CommentsLoadFailure | "reload";

interface UseCommentPaginatedReturn {
  commentsPage: PaginatedComments | null;
  hasMoreComments: boolean;
  increaseRepliesCount: (commentId: number) => void;
  isLoadingMore: boolean;
  isRefreshing: boolean;
  loadFailure: CommentsLoadFailure | null;
  loadMoreComments: () => void;
  reloadComments: (afterReload?: () => void) => void;
  retryLoad: () => void;
  updateCommentContent: (commentId: number, content: string) => void;
  uploadedComments: () => void;
}

/**
 * Load, page and refresh the comments of a post or the replies of a comment.
 * The target is fixed for the lifetime of the component that calls it: remount it (key) to change it.
 */
export function useCommentPaginated(
  commentableType: CommentTypeValues,
  commentableId: number,
): UseCommentPaginatedReturn {
  const [commentsPage, setCommentsPage] = useState<PaginatedComments | null>(null);
  const [isLoadingMore, setIsLoadingMore] = useState<boolean>(false);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(true);
  const [loadFailure, setLoadFailure] = useState<CommentsLoadFailure | null>(null);

  const isRequestInFlight = useRef<boolean>(false);

  const isPostThread = commentableType === CommentType.POST;

  const propName = isPostThread ? "comments" : "replies";
  const targetParameter = isPostThread ? "comments_post" : "replies_comment";
  const cursorParameter = isPostThread ? "comments_cursor" : "replies_cursor";

  const nextCursor = commentsPage?.next_cursor ?? null;
  const hasMoreComments = nextCursor !== null;

  const sendRequest = useCallback(
    (cursor: string | null, kind: CommentRequestKind, afterFinish?: () => void) => {
      if (isRequestInFlight.current) return;

      isRequestInFlight.current = true;

      let hasLoaded = false;

      router.reload({
        data: {
          [targetParameter]: commentableId,
          ...(cursor === null ? {} : { [cursorParameter]: cursor }),
        },
        only: [propName],
        preserveUrl: true,
        replace: true,
        reset: [propName],
        onHttpException: () => false,
        onNetworkError: () => false,
        onSuccess: (page) => {
          const incomingPage = (page.props as Record<string, PaginatedComments | undefined>)[
            propName
          ];

          if (!incomingPage?.data) return;

          hasLoaded = true;

          setCommentsPage((loadedPage) => {
            if (loadedPage === null || kind === "reload") return incomingPage;

            const incomingIds = new Set<number>(incomingPage.data.map((comment) => comment.id));
            const keptComments = loadedPage.data.filter((comment) => !incomingIds.has(comment.id));

            if (kind === "more") {
              return { ...incomingPage, data: [...keptComments, ...incomingPage.data] };
            }

            if (loadedPage.next_cursor !== null) return incomingPage;

            return {
              ...incomingPage,
              data: [...incomingPage.data, ...keptComments],
              next_cursor: null,
              next_page_url: null,
            };
          });
        },
        onFinish: () => {
          isRequestInFlight.current = false;

          setIsRefreshing(false);
          setIsLoadingMore(false);

          if (!hasLoaded) setLoadFailure(kind === "reload" ? "refresh" : kind);

          afterFinish?.();
        },
      });
    },
    [commentableId, cursorParameter, propName, targetParameter],
  );

  const startRequest = useCallback(
    (cursor: string | null, kind: CommentRequestKind, afterFinish?: () => void) => {
      if (isRequestInFlight.current) return;

      setLoadFailure(null);

      if (kind === "more") {
        setIsLoadingMore(true);
      } else {
        setIsRefreshing(true);
      }

      sendRequest(cursor, kind, afterFinish);
    },
    [sendRequest],
  );

  const uploadedComments = useCallback(() => {
    startRequest(null, "refresh");
  }, [startRequest]);

  const reloadComments = useCallback(
    (afterReload?: () => void) => {
      startRequest(null, "reload", afterReload);
    },
    [startRequest],
  );

  const loadMoreComments = useCallback(() => {
    if (nextCursor === null || loadFailure !== null) return;

    startRequest(nextCursor, "more");
  }, [loadFailure, nextCursor, startRequest]);

  const retryLoad = useCallback(() => {
    if (loadFailure === null) return;

    startRequest(loadFailure === "refresh" ? null : nextCursor, loadFailure);
  }, [loadFailure, nextCursor, startRequest]);

  const increaseRepliesCount = useCallback((commentId: number) => {
    setCommentsPage(
      (loadedPage) =>
        loadedPage && {
          ...loadedPage,
          data: loadedPage.data.map((comment) =>
            comment.id === commentId
              ? {
                  ...comment,
                  repliesInfo: {
                    hasReplies: true,
                    repliesCount: comment.repliesInfo.repliesCount + 1,
                  },
                }
              : comment,
          ),
        },
    );
  }, []);

  const updateCommentContent = useCallback((commentId: number, content: string) => {
    setCommentsPage(
      (loadedPage) =>
        loadedPage && {
          ...loadedPage,
          data: loadedPage.data.map((comment) =>
            comment.id === commentId && comment.content !== content
              ? { ...comment, content, editedAt: new Date().toISOString() }
              : comment,
          ),
        },
    );
  }, []);

  useEffect(() => {
    sendRequest(null, "refresh");
  }, [sendRequest]);

  return {
    commentsPage,
    hasMoreComments,
    increaseRepliesCount,
    isLoadingMore,
    isRefreshing,
    loadFailure,
    loadMoreComments,
    reloadComments,
    retryLoad,
    updateCommentContent,
    uploadedComments,
  };
}
