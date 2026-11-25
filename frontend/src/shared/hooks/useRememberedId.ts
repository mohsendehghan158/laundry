"use client";

import { useState, useSyncExternalStore } from "react";

function read(key: string): number | null {
  try {
    return Number(window.localStorage.getItem(key)) || null;
  } catch {
    return null;
  }
}

/**
 * An id chosen on this device (a station, a branch) that survives reloads:
 * the choice made now, else the one remembered, else null.
 */
export function useRememberedId(key: string): [number | null, (id: number | null) => void] {
  const [chosen, setChosen] = useState<number | null | undefined>(undefined);
  const remembered = useSyncExternalStore(
    () => () => {},
    () => read(key),
    () => null,
  );

  const choose = (id: number | null) => {
    setChosen(id);
    try {
      if (id === null) {
        window.localStorage.removeItem(key);
      } else {
        window.localStorage.setItem(key, String(id));
      }
    } catch {
      // Private mode: the choice simply is not remembered.
    }
  };

  return [chosen === undefined ? remembered : chosen, choose];
}
