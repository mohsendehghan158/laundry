"use client";

import { Table, type TableProps } from "antd";
import type { SorterResult } from "antd/es/table/interface";
import { useTranslations } from "next-intl";
import type { Paginated } from "@/shared/api/types";

type Props<T> = Omit<TableProps<T>, "dataSource" | "pagination" | "onChange"> & {
  result: Paginated<T> | undefined;
  loading?: boolean;
  /** Current sort in API form: "name" or "-name". */
  sort?: string | null;
  onPageChange: (page: number, perPage: number) => void;
  onSortChange?: (sort: string | null) => void;
};

/** Converts antd's sorter into the API's `sort` parameter and back. */
export function toSortParam(sorter: SorterResult<unknown> | SorterResult<unknown>[]): string | null {
  const single = Array.isArray(sorter) ? sorter[0] : sorter;
  if (!single?.order || !single.columnKey) {
    return null;
  }
  return `${single.order === "descend" ? "-" : ""}${String(single.columnKey)}`;
}

export function sortOrderFor(sort: string | null | undefined, key: string): "ascend" | "descend" | null {
  if (sort === key) {
    return "ascend";
  }
  if (sort === `-${key}`) {
    return "descend";
  }
  return null;
}

/**
 * Server-paginated table: rows, page and total come from a Laravel
 * paginated response. Columns opt into sorting with `sorter: true` and a
 * `key` equal to the API sort field.
 */
export function DataTable<T extends { id: number }>({ result, loading, sort, onPageChange, onSortChange, columns, ...rest }: Props<T>) {
  const t = useTranslations("common");

  const sortedColumns = columns?.map((column) =>
    column.sorter && column.key ? { ...column, sortOrder: sortOrderFor(sort, String(column.key)) } : column,
  );

  return (
    <Table<T>
      rowKey="id"
      size="middle"
      scroll={{ x: "max-content" }}
      {...rest}
      columns={sortedColumns}
      loading={loading}
      dataSource={result?.data ?? []}
      pagination={{
        current: result?.meta.current_page ?? 1,
        pageSize: result?.meta.per_page ?? 25,
        total: result?.meta.total ?? 0,
        showSizeChanger: true,
        pageSizeOptions: [10, 25, 50, 100],
        showTotal: (total) => t("total", { count: total }),
      }}
      onChange={(pagination, _filters, sorter, extra) => {
        if (extra.action === "sort") {
          onSortChange?.(toSortParam(sorter as SorterResult<unknown>));
          return;
        }
        onPageChange(pagination.current ?? 1, pagination.pageSize ?? 25);
      }}
    />
  );
}
