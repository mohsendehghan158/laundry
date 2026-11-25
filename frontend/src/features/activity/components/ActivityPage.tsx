"use client";

import { Card, Flex, Select, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { parseAsIsoDateTime, parseAsStringLiteral } from "nuqs";
import type { Activity } from "@/shared/api/types";
import { useListParams } from "@/shared/hooks/useListParams";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { DataTable } from "@/shared/ui/DataTable";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { PageHeader } from "@/shared/ui/PageHeader";
import { EVENTS, LOG_NAMES, useActivityLog } from "../api";

function display(value: unknown): string {
  if (value === null || value === undefined || value === "") {
    return "—";
  }
  if (typeof value === "boolean") {
    return value ? "✓" : "✗";
  }
  return typeof value === "object" ? JSON.stringify(value) : String(value);
}

function Changes({ activity }: { activity: Activity }) {
  const t = useTranslations("activity");
  const after = activity.changes?.attributes ?? {};
  const before = activity.changes?.old ?? {};
  const fields = Array.from(new Set([...Object.keys(after), ...Object.keys(before)]));

  if (fields.length === 0) {
    const properties = activity.properties && Object.keys(activity.properties).length > 0 ? activity.properties : null;
    return properties ? (
      <pre className="ltr" style={{ margin: 0, whiteSpace: "pre-wrap" }}>
        {JSON.stringify(properties, null, 2)}
      </pre>
    ) : (
      <Typography.Text type="secondary">{t("noChanges")}</Typography.Text>
    );
  }

  return (
    <Table
      size="small"
      pagination={false}
      rowKey="field"
      dataSource={fields.map((field) => ({ field, before: display(before[field]), after: display(after[field]) }))}
      columns={[
        { key: "field", dataIndex: "field", title: t("field"), render: (v: string) => <code>{v}</code> },
        { key: "before", dataIndex: "before", title: t("before") },
        { key: "after", dataIndex: "after", title: t("after") },
      ]}
    />
  );
}

export function ActivityPage() {
  const t = useTranslations("activity");
  const [params, setParams, setFilter] = useListParams({
    area: parseAsStringLiteral(LOG_NAMES),
    event: parseAsStringLiteral(EVENTS),
    from: parseAsIsoDateTime,
    to: parseAsIsoDateTime,
  });

  const log = useActivityLog({
    log_name: params.area,
    event: params.event,
    from: params.from?.toISOString(),
    to: params.to?.toISOString(),
    page: params.page,
    per_page: params.per_page,
  });

  const label = (group: "logNames" | "events", value: string | null) =>
    value && t.has(`${group}.${value}` as never) ? t(`${group}.${value}` as never) : value ?? "—";

  const columns: ColumnsType<Activity> = [
    { key: "created_at", dataIndex: "created_at", title: t("columns.time"), render: (v: string | null) => formatDateTime(v) },
    {
      key: "causer",
      dataIndex: "causer",
      title: t("columns.causer"),
      render: (causer: Activity["causer"]) => causer?.name ?? <Typography.Text type="secondary">{t("system")}</Typography.Text>,
    },
    { key: "log_name", dataIndex: "log_name", title: t("columns.area"), render: (v: string | null) => label("logNames", v) },
    {
      key: "event",
      dataIndex: "event",
      title: t("columns.event"),
      render: (v: string | null) => <Tag color={v === "deleted" ? "red" : v === "created" ? "green" : undefined}>{label("events", v)}</Tag>,
    },
    { key: "subject", dataIndex: "subject_label", title: t("columns.subject"), render: (v: string | null) => v ?? "—" },
  ];

  return (
    <>
      <PageHeader title={t("title")} />
      <Card>
        <Flex gap={8} wrap style={{ marginBottom: 16 }}>
          <Select
            allowClear
            placeholder={t("filters.area")}
            value={params.area}
            onChange={(area) => setFilter({ area: area ?? null })}
            options={LOG_NAMES.map((name) => ({ value: name, label: label("logNames", name) }))}
            style={{ width: 170 }}
          />
          <Select
            allowClear
            placeholder={t("filters.event")}
            value={params.event}
            onChange={(event) => setFilter({ event: event ?? null })}
            options={EVENTS.map((name) => ({ value: name, label: label("events", name) }))}
            style={{ width: 150 }}
          />
          <JalaliRangePicker
            allowEmpty={[true, true]}
            placeholder={[t("filters.from"), t("filters.to")]}
            value={[params.from ? dayjs(params.from) : null, params.to ? dayjs(params.to) : null]}
            onChange={(range) =>
              setFilter({
                from: range?.[0] ? range[0].startOf("day").toDate() : null,
                to: range?.[1] ? range[1].endOf("day").toDate() : null,
              })
            }
            format="YYYY/MM/DD"
          />
        </Flex>
        <DataTable<Activity>
          columns={columns}
          result={log.data}
          loading={log.isFetching}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
          expandable={{ expandedRowRender: (activity) => <Changes activity={activity} /> }}
        />
      </Card>
    </>
  );
}

