"use client";

import { Card, Table, type TableProps } from "antd";
import type { SorterResult, SortOrder } from "antd/es/table/interface";
import type { ReactNode } from "react";
import { useTranslations } from "next-intl";
import type { Paginated } from "@/shared/api/types";
import { formatNumber } from "@/shared/lib/digits";
import type { TableState } from "@/shared/hooks/useTableState";

export interface DataTableProps<T> extends Omit<TableProps<T>, "dataSource" | "pagination" | "onChange" | "loading"> {
  result?: Paginated<T>;
  loading?: boolean;
  /** Page and sort live in the address bar (see useTableState). */
  state: TableState;
  toolbar?: ReactNode;
}

/**
 * Server-paginated table: page, page size and sort are read from and written
 * to the URL, so links can be shared and the back button works.
 */
export function DataTable<T extends { id: number | string }>({
  result,
  loading,
  state,
  toolbar,
  columns,
  ...rest
}: DataTableProps<T>) {
  const t = useTranslations("common");

  const sortedColumns = columns?.map((column) => {
    const key = "dataIndex" in column ? String(column.dataIndex) : String(column.key);
    if (!column.sorter) return column;
    const order: SortOrder = state.sort === key ? "ascend" : state.sort === `-${key}` ? "descend" : null;
    return { ...column, sortOrder: order };
  });

  return (
    <Card styles={{ body: { padding: 0 } }}>
      {toolbar ? <div style={{ padding: 16, paddingBottom: 0 }}>{toolbar}</div> : null}
      <Table<T>
        rowKey="id"
        scroll={{ x: "max-content" }}
        {...rest}
        columns={sortedColumns}
        dataSource={result?.data}
        loading={loading}
        style={{ padding: 16 }}
        pagination={{
          current: state.page,
          pageSize: state.perPage,
          total: result?.meta.total ?? 0,
          showSizeChanger: true,
          pageSizeOptions: [10, 25, 50, 100],
          showTotal: (total) => t("total", { count: formatNumber(total) }),
        }}
        onChange={(pagination, _filters, sorter) => {
          const single = (Array.isArray(sorter) ? sorter[0] : sorter) as SorterResult<T> | undefined;
          const field = single?.order ? String(single.columnKey ?? single.field) : null;
          state.set({
            page: pagination.current ?? 1,
            perPage: pagination.pageSize ?? state.perPage,
            sort: field ? (single?.order === "descend" ? `-${field}` : field) : null,
          });
        }}
      />
    </Card>
  );
}
