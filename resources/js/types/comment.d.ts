export interface Comment {
  content: string;
  created_at: string;
  id: number;
  replies?: Comment[];
  user: User;
}
