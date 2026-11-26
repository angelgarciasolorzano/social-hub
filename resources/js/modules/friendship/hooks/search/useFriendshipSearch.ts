import { useCallback, useEffect, useRef, useState } from "react";

import { router } from "@inertiajs/react";

import FriendshipSearchController from "@/shared/wayfinder/actions/App/Modules/Friendship/Controllers/FriendshipSearchController";

interface UseFriendshipSearchReturn {
  committedTerm: string;
  isSearching: boolean;
  term: string;
  updateTerm: (value: string) => void;
}

/**
 * Search term of the people search page, kept in the URL (`?q=`) so a search can be shared and reloaded.
 * Typing is debounced; each search replaces the scrolled results instead of merging into them.
 * `committedTerm` is the term of the rendered results, for empty states that must not flash while typing.
 *
 * @param initialTerm The term the server rendered the page with.
 * @param delay       Debounce delay in ms (default 400).
 */
export function useFriendshipSearch(initialTerm: string, delay = 400): UseFriendshipSearchReturn {
  const [term, setTerm] = useState<string>(initialTerm);
  const [committedTerm, setCommittedTerm] = useState<string>(initialTerm);
  const [isSearching, setIsSearching] = useState<boolean>(false);

  const hasInteractedRef = useRef<boolean>(false);

  useEffect(() => {
    if (!hasInteractedRef.current) return;

    const timer = setTimeout(() => {
      const trimmedTerm = term.trim();

      router.get(FriendshipSearchController.url(), trimmedTerm === "" ? {} : { q: trimmedTerm }, {
        only: ["searchResults", "filters"],
        reset: ["searchResults"],
        preserveState: true,
        preserveScroll: true,
        replace: true,
        onStart: () => {
          setIsSearching(true);
        },
        onFinish: () => {
          setIsSearching(false);
          setCommittedTerm(trimmedTerm);
        },
      });
    }, delay);

    return () => {
      clearTimeout(timer);
    };
  }, [term, delay]);

  const updateTerm = useCallback((value: string): void => {
    hasInteractedRef.current = true;

    setTerm(value);
  }, []);

  return { committedTerm, isSearching, term, updateTerm };
}
