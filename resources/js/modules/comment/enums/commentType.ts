export const CommentType = {
  COMMENT: "comment",
  POST: "post",
} as const;

export type CommentTypeValues = (typeof CommentType)[keyof typeof CommentType];
