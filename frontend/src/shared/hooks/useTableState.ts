"use client";

import { parseAsInteger, parseAsString, useQueryStates } from "nuqs";
import { useCallback, useMemo } from "react";

export interface TableState {
  page: number;
  perPage: number;
  sort: string | null;
  filters: Record<string, string | null>;
  set: (patch: { page?: number; perPage?: number; sort?: string | null }) => void;
  setFilter: (name: string, value: string | null) => void;
  /** Query parameters in the API's format: page, per_page, sort, filter[x]. */
  query: Record<string, string | number | null>;
}

/**
 * Page, page size, sort and filters of a list, kept in the address bar so a
 * filtered list can be shared and the back button restores it.
 */
export function useTableState(filterNames: string[], defaultSort: string | null = null): TableState {
  const key = filterNames.join(",");
  const parsers = useMemo(
    () => ({
      page: parseAsInteger.withDefault(1),
      per_page: parseAsInteger.withDefault(25),
      sort: parseAsString,
      ...Object.fromEntries((key ? key.split(",") : []).map((name) => [name, parseAsString])),
    }),
    [key],
  );

  const [values, setValues] = useQueryStates(parsers, { history: "replace" });
  const record = values as Record<string, string | number | null>;

  const filters = Object.fromEntries(filterNames.map((name) => [name, (record[name] as string | null) ?? null]));
  const sort = (record.sort as string | null) ?? defaultSort;

  const set = useCallback<TableState["set"]>(
    (patch) => {
      void setValues({
        ...(patch.page !== undefined ? { page: patch.page === 1 ? null : patch.page } : {}),
        ...(patch.perPage !== undefined ? { per_page: patch.perPage === 25 ? null : patch.perPage } : {}),
        ...(patch.sort !== undefined ? { sort: patch.sort } : {}),
      } as never);
    },
    [setValues],
  );

  const setFilter = useCallback(
    (name: string, value: string | null) => {
      // A new filter starts from the first page.
      void setValues({ [name]: value === "" ? null : value, page: null } as never);
    },
    [setValues],
  );

  const query: Record<string, string | number | null> = {
    page: record.page as number,
    per_page: record.per_page as number,
    sort,
    ...Object.fromEntries(filterNames.map((name) => [`filter[${name}]`, filters[name]])),
  };

  return {
    page: record.page as number,
    perPage: record.per_page as number,
    sort,
    filters,
    set,
    setFilter,
    query,
  };
}
