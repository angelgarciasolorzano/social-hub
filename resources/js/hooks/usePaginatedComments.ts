import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import CommentController from "@/actions/App/Comment/Controllers/CommentController";

import { CommentableType } from "@/enums";

import { hasPaginatedKey } from "@/utils";

import { Comment, PaginatedComments } from "@/types";

interface UsePaginatedCommentsReturn {
  commentsPage: PaginatedComments | null;
  hasMoreComments: boolean;
  increaseRepliesCount: (commentId: number) => void;
  isLoadingMore: boolean;
  isRefreshing: boolean;
  loadMoreComments: () => void;
  uploadedComments: () => void;
}

export function usePaginatedComments(
  commentableType: CommentableType,
  commentableId: number,
): UsePaginatedCommentsReturn {
  const [commentsPage, setCommentsPage] = useState<PaginatedComments | null>(null);
  const [isLoadingMore, setIsLoadingMore] = useState(false);
  const [isRefreshing, setIsRefreshing] = useState(false);

  const loadingRef = useRef(false);
  const lastRequestedUrl = useRef<string | null>(null);

  const hasMoreComments = !!commentsPage?.links.next;

  const uploadedComments = useCallback(() => {
    if (loadingRef.current) return;

    loadingRef.current = true;
    setIsRefreshing(true);

    setTimeout(() => {
      router.get(
        CommentController.index.url({ commentableType, commentableId }),
        {},
        {
          preserveState: true,
          replace: true,
          onSuccess: (data) => {
            if (hasPaginatedKey<Comment>(data.flash, "comments")) {
              const incoming = data.flash.comments as PaginatedComments;

              setCommentsPage((prev) => {
                if (!prev) return incoming;

                const wasFullyLoaded = prev.links.next === null;

                if (!wasFullyLoaded) return incoming;

                const map = new Map<number, Comment>();

                prev.data.forEach((comment) => map.set(comment.id, comment));
                incoming.data.forEach((comment) => map.set(comment.id, comment));

                return {
                  ...incoming,
                  data: Array.from(map.values()),
                  links: {
                    ...incoming.links,
                    next: null,
                  },
                };
              });
            }
          },
          onFinish: () => {
            loadingRef.current = false;
            setIsRefreshing(false);
          },
        },
      );
    }, 300);
  }, [commentableType, commentableId]);

  const loadMoreComments = useCallback(() => {
    if (loadingRef.current) return;
    if (!commentsPage?.links.next) return;
    if (lastRequestedUrl.current === commentsPage.links.next) return;

    loadingRef.current = true;
    lastRequestedUrl.current = commentsPage.links.next;

    setIsLoadingMore(true);

    router.get(
      commentsPage.links.next,
      {},
      {
        preserveState: true,
        replace: true,
        onSuccess: (data) => {
          if (hasPaginatedKey<Comment>(data.flash, "comments")) {
            setCommentsPage((prev) => {
              const newPageData = data.flash.comments as PaginatedComments;

              if (!prev) return newPageData;

              const map = new Map<number, Comment>();

              prev.data.forEach((comment) => map.set(comment.id, comment));
              newPageData.data.forEach((comment) => map.set(comment.id, comment));

              return {
                ...newPageData,
                data: Array.from(map.values()),
              };
            });
          }
        },
        onFinish: () => {
          loadingRef.current = false;
          setIsLoadingMore(false);
        },
      },
    );
  }, [commentsPage?.links.next]);

  const increaseRepliesCount = useCallback((commentId: number) => {
    setCommentsPage((prev) => {
      if (!prev) {
        return prev;
      }

      return {
        ...prev,
        data: prev.data.map((comment) => {
          if (comment.id !== commentId) {
            return comment;
          }

          return {
            ...comment,
            repliesInfo: {
              hasReplies: true,
              repliesCount: comment.repliesInfo.repliesCount + 1,
            },
          };
        }),
      };
    });
  }, []);

  useEffect(() => {
    if (commentsPage?.links.next && lastRequestedUrl.current !== commentsPage.links.next) {
      lastRequestedUrl.current = null;
    }
  }, [commentsPage?.links.next]);

  useEffect(() => {
    uploadedComments();
  }, [uploadedComments]);

  return {
    commentsPage,
    hasMoreComments,
    increaseRepliesCount,
    isLoadingMore,
    isRefreshing,
    loadMoreComments,
    uploadedComments,
  };
}
