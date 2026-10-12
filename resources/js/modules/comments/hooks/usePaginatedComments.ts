import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { CommentableType } from "../enums/commentableType";
import type { CommentableTypeValues } from "../enums/commentableType";
import type { Comment, CommentsLoadFailure, PaginatedComments } from "../types/comment";

interface CommentThreadRequest {
  cursorParameter: string;
  prop: "comments" | "replies";
  targetParameter: string;
}

const COMMENT_THREAD_REQUESTS: Record<CommentableTypeValues, CommentThreadRequest> = {
  [CommentableType.POST]: {
    cursorParameter: "comments_cursor",
    prop: "comments",
    targetParameter: "comments_post",
  },
  [CommentableType.COMMENT]: {
    cursorParameter: "replies_cursor",
    prop: "replies",
    targetParameter: "replies_comment",
  },
};

interface UsePaginatedCommentsReturn {
  commentsPage: PaginatedComments | null;
  hasMoreComments: boolean;
  increaseRepliesCount: (commentId: number) => void;
  isLoadingMore: boolean;
  isRefreshing: boolean;
  loadFailure: CommentsLoadFailure | null;
  loadMoreComments: () => void;
  retryLoad: () => void;
  uploadedComments: () => void;
}

function isPaginatedComments(candidate: unknown): candidate is PaginatedComments {
  if (typeof candidate !== "object" || candidate === null) return false;

  const page = candidate as Partial<Record<keyof PaginatedComments, unknown>>;

  return Array.isArray(page.data) && "next_cursor" in page;
}

function withoutCommentsOf(comments: Comment[], excluded: Comment[]): Comment[] {
  const excludedIds = new Set<number>(excluded.map((comment) => comment.id));

  return comments.filter((comment) => !excludedIds.has(comment.id));
}

/**
 * Combine the comments already loaded with a freshly received page, never repeating an id.
 * A next page goes after what is loaded; a refresh goes before it and only keeps the rest
 * when everything was already loaded, otherwise the refreshed first page replaces it.
 */
function combineCommentPages(
  loadedPage: PaginatedComments | null,
  incomingPage: PaginatedComments,
  kind: CommentsLoadFailure,
): PaginatedComments {
  if (loadedPage === null) return incomingPage;

  if (kind === "more") {
    return {
      ...incomingPage,
      data: [...withoutCommentsOf(loadedPage.data, incomingPage.data), ...incomingPage.data],
    };
  }

  if (loadedPage.next_cursor !== null) return incomingPage;

  return {
    ...incomingPage,
    data: [...incomingPage.data, ...withoutCommentsOf(loadedPage.data, incomingPage.data)],
    next_cursor: null,
    next_page_url: null,
  };
}

/**
 * Load, page and refresh the comments of a post or the replies of a comment.
 * The target is fixed for the lifetime of the component that calls it: remount it (key) to change it.
 */
export function usePaginatedComments(
  commentableType: CommentableTypeValues,
  commentableId: number,
): UsePaginatedCommentsReturn {
  const [commentsPage, setCommentsPage] = useState<PaginatedComments | null>(null);
  const [isLoadingMore, setIsLoadingMore] = useState<boolean>(false);
  const [isRefreshing, setIsRefreshing] = useState<boolean>(true);
  const [loadFailure, setLoadFailure] = useState<CommentsLoadFailure | null>(null);

  const loadingRef = useRef<boolean>(false);
  const loadFailureRef = useRef<CommentsLoadFailure | null>(null);
  const commentsPageRef = useRef<PaginatedComments | null>(null);

  const { cursorParameter, prop, targetParameter } = COMMENT_THREAD_REQUESTS[commentableType];

  const nextCursor = commentsPage?.next_cursor ?? null;
  const hasMoreComments = nextCursor !== null;

  const storeCommentsPage = useCallback((page: PaginatedComments | null) => {
    commentsPageRef.current = page;
    setCommentsPage(page);
  }, []);

  const storeLoadFailure = useCallback((failure: CommentsLoadFailure | null) => {
    loadFailureRef.current = failure;
    setLoadFailure(failure);
  }, []);

  const sendRequest = useCallback(
    (cursor: string | null, kind: CommentsLoadFailure) => {
      if (loadingRef.current) return;

      loadingRef.current = true;

      let hasLoaded = false;

      router.reload({
        data: {
          [targetParameter]: commentableId,
          ...(cursor === null ? {} : { [cursorParameter]: cursor }),
        },
        only: [prop],
        preserveUrl: true,
        replace: true,
        reset: [prop],
        onHttpException: () => false,
        onNetworkError: () => false,
        onSuccess: (page) => {
          const incomingPage: unknown = (page.props as Record<string, unknown>)[prop];

          if (!isPaginatedComments(incomingPage)) return;

          hasLoaded = true;

          storeCommentsPage(combineCommentPages(commentsPageRef.current, incomingPage, kind));
        },
        onFinish: () => {
          loadingRef.current = false;

          setIsRefreshing(false);
          setIsLoadingMore(false);

          if (!hasLoaded) storeLoadFailure(kind);
        },
      });
    },
    [commentableId, cursorParameter, prop, storeCommentsPage, storeLoadFailure, targetParameter],
  );

  const startRequest = useCallback(
    (cursor: string | null, kind: CommentsLoadFailure) => {
      if (loadingRef.current) return;

      storeLoadFailure(null);

      if (kind === "refresh") {
        setIsRefreshing(true);
      } else {
        setIsLoadingMore(true);
      }

      sendRequest(cursor, kind);
    },
    [sendRequest, storeLoadFailure],
  );

  const uploadedComments = useCallback(() => {
    startRequest(null, "refresh");
  }, [startRequest]);

  const loadMoreComments = useCallback(() => {
    if (loadFailureRef.current !== null) return;

    const cursor = commentsPageRef.current?.next_cursor ?? null;

    if (cursor === null) return;

    startRequest(cursor, "more");
  }, [startRequest]);

  const retryLoad = useCallback(() => {
    const failure = loadFailureRef.current;

    if (failure === null) return;

    startRequest(
      failure === "refresh" ? null : (commentsPageRef.current?.next_cursor ?? null),
      failure,
    );
  }, [startRequest]);

  const increaseRepliesCount = useCallback(
    (commentId: number) => {
      const loadedPage = commentsPageRef.current;

      if (loadedPage === null) return;

      storeCommentsPage({
        ...loadedPage,
        data: loadedPage.data.map((comment: Comment) => {
          if (comment.id !== commentId) return comment;

          return {
            ...comment,
            repliesInfo: {
              hasReplies: true,
              repliesCount: comment.repliesInfo.repliesCount + 1,
            },
          };
        }),
      });
    },
    [storeCommentsPage],
  );

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
    retryLoad,
    uploadedComments,
  };
}
