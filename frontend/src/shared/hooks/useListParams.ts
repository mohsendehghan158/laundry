"use client";

import { parseAsInteger, parseAsString, useQueryStates, type ParserMap } from "nuqs";

const base = {
  page: parseAsInteger.withDefault(1),
  per_page: parseAsInteger.withDefault(25),
  sort: parseAsString,
  q: parseAsString.withDefault(""),
};

/**
 * Page, page size, sort and search of a list, kept in the address bar so a
 * filtered list can be shared and the back button works. Extra filters are
 * passed as nuqs parsers. Changing any filter returns to page 1.
 */
export function useListParams<P extends ParserMap>(filters: P = {} as P) {
  const [params, setParams] = useQueryStates({ ...base, ...filters }, { history: "replace" });

  const setFilter = (patch: Partial<{ [K in keyof typeof params]: (typeof params)[K] | null }>) =>
    setParams({ ...patch, page: null } as never);

  return [params, setParams, setFilter] as const;
}
