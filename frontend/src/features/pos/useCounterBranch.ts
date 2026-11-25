"use client";

import { useMemo, useState, useSyncExternalStore } from "react";
import { usePermission } from "@/shared/hooks/usePermission";

const BRANCH_KEY = "pos.branchId";

function readRememberedBranch(): number | null {
  try {
    return Number(window.localStorage.getItem(BRANCH_KEY)) || null;
  } catch {
    return null;
  }
}

/**
 * The counter branch this device works for: the user's choice, else the
 * branch remembered on this device, else the first one they can use.
 * Shared by the counter and the register so both use the same branch.
 */
export function useCounterBranch() {
  const { me } = usePermission();
  const branches = useMemo(() => (me?.branches ?? []).filter((branch) => branch.type !== "plant"), [me]);
  const [chosen, setChosen] = useState<number | null>(null);
  const remembered = useSyncExternalStore(
    () => () => {},
    () => readRememberedBranch(),
    () => null,
  );
  const branchId = [chosen, remembered].find((id) => id !== null && branches.some((b) => b.id === id)) ?? branches[0]?.id ?? null;

  const choose = (id: number) => {
    setChosen(id);
    try {
      window.localStorage.setItem(BRANCH_KEY, String(id));
    } catch {
      // Private mode: the choice simply is not remembered.
    }
  };

  return { branches, branchId, choose };
}
