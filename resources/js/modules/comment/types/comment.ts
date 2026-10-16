import type { User } from "@/shared/types";

import type { CommentTypeValues } from "../enums/commentType";

interface CommentReplies {
  hasReplies: boolean;
  repliesCount: number;
}

export interface CommentLikesInfo {
  count: number;
  likedByMe: boolean;
}

interface CommentRepliedTo {
  id: number;
  name: string;
}

export interface Comment {
  content: string;
  createdAt: string;
  id: number;
  likesInfo: CommentLikesInfo;
  repliedTo?: CommentRepliedTo | null;
  repliesInfo: CommentReplies;
  user: User;
}

export type CommentFormData = Pick<Comment, "content"> & {
  commentable_id: number;
  commentable_type: CommentTypeValues;
};

export interface PaginatedComments {
  data: Comment[];
  next_cursor: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_cursor: string | null;
  prev_page_url: string | null;
}

export interface CommentLikeToggleResponse {
  liked: boolean;
  likesCount: number;
}

export type CommentsLoadFailure = "more" | "refresh";

export interface CommentContextPreview {
  authorName: string;
  authorProfilePicture: string | null | undefined;
  content: string;
  createdAt: string;
  kind: CommentTypeValues;
}
