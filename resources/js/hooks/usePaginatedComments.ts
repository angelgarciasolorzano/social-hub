import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import { CommentableType } from "@/enums";

import CommentController from "@/actions/App/Comment/Controllers/CommentController";

import { hasPaginatedKey } from "@/utils";

import { Comment, PaginatedComments } from "@/types";

interface UsePaginatedCommentsReturn {
  commentsPage: PaginatedComments | null;
  hasMoreComments: boolean;
  loading: boolean;
  loadMoreComments: () => void;
  uploadedComments: () => void;
}

export function usePaginatedComments(
  commentableType: CommentableType,
  commentableId: number,
): UsePaginatedCommentsReturn {
  const [commentsPage, setCommentsPage] = useState<PaginatedComments | null>(null);
  const [loading, setLoading] = useState(false);

  const loadingRef = useRef(false);
  const lastRequestedUrl = useRef<string | null>(null);

  const hasMoreComments = !!commentsPage?.links.next;

  const uploadedComments = useCallback(() => {
    if (loadingRef.current) return;

    loadingRef.current = true;
    setLoading(true);

    setTimeout(() => {
      router.get(
        CommentController.index.url({ commentableType, commentableId }),
        {},
        {
          preserveState: true,
          replace: true,
          onSuccess: (data) => {
            if (hasPaginatedKey<Comment>(data.flash, "comments")) {
              setCommentsPage(data.flash.comments as PaginatedComments);
            }
          },
          onFinish: () => {
            loadingRef.current = false;
            setLoading(false);
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

    setLoading(true);

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
          setLoading(false);
        },
      },
    );
  }, [commentsPage?.links.next]);

  useEffect(() => {
    if (commentsPage?.links.next && lastRequestedUrl.current !== commentsPage.links.next) {
      lastRequestedUrl.current = null;
    }
  }, [commentsPage?.links.next]);

  useEffect(() => {
    uploadedComments();
  }, [uploadedComments]);

  return { commentsPage, loading, loadMoreComments, uploadedComments, hasMoreComments };
}
