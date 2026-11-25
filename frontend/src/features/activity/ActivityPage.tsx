"use client";

import { Descriptions, Flex, Select, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import type { Activity } from "@/shared/api/types";
import { useTableState } from "@/shared/hooks/useTableState";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { DataTable } from "@/shared/ui/DataTable";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useActivity } from "./api";

const AREAS = ["auth", "user", "branch", "role", "settings", "organization"];
const EVENTS = ["created", "updated", "deleted", "login", "logout", "roles_updated"];
const EVENT_COLORS: Record<string, string> = { created: "green", updated: "blue", deleted: "red", login: "cyan", logout: "default", roles_updated: "purple" };

export function ActivityPage() {
  const t = useTranslations("activity");
  const table = useTableState(["log_name", "event", "causer_id", "from", "to"]);
  const activity = useActivity(table.query);

  const label = (group: "areas" | "events", key: string | null) =>
    key ? (t.has(`${group}.${key}`) ? t(`${group}.${key}`) : key) : "—";

  const columns: ColumnsType<Activity> = [
    { title: t("when"), dataIndex: "created_at", key: "created_at", render: (v: string) => formatDateTime(v), width: 150 },
    { title: t("who"), dataIndex: "causer", key: "causer", render: (causer: Activity["causer"]) => causer?.name ?? t("system") },
    { title: t("area"), dataIndex: "log_name", key: "log_name", render: (v: string | null) => label("areas", v) },
    {
      title: t("event"),
      dataIndex: "event",
      key: "event",
      render: (v: string | null) => <Tag color={EVENT_COLORS[v ?? ""] ?? "default"}>{label("events", v)}</Tag>,
    },
    { title: t("subject"), dataIndex: "subject_label", key: "subject_label", render: (v: string | null) => v ?? "—" },
  ];

  const range =
    table.filters.from || table.filters.to
      ? ([table.filters.from ? dayjs(table.filters.from) : null, table.filters.to ? dayjs(table.filters.to) : null] as const)
      : null;

  return (
    <>
      <PageHeader title={t("title")} />
      <DataTable<Activity>
        state={table}
        result={activity.data}
        loading={activity.isFetching}
        columns={columns}
        expandable={{
          rowExpandable: (row) => Boolean(row.changes?.attributes || row.changes?.old || row.properties),
          expandedRowRender: (row) => <ActivityChanges activity={row} />,
        }}
        toolbar={
          <Flex gap={8} wrap>
            <Select
              allowClear
              placeholder={t("area")}
              value={table.filters.log_name ?? undefined}
              onChange={(value) => table.setFilter("log_name", value ?? null)}
              options={AREAS.map((area) => ({ value: area, label: label("areas", area) }))}
              style={{ minWidth: 150 }}
            />
            <Select
              allowClear
              placeholder={t("event")}
              value={table.filters.event ?? undefined}
              onChange={(value) => table.setFilter("event", value ?? null)}
              options={EVENTS.map((event) => ({ value: event, label: label("events", event) }))}
              style={{ minWidth: 150 }}
            />
            <JalaliRangePicker
              allowEmpty={[true, true]}
              placeholder={[t("from"), t("to")]}
              value={range as never}
              onChange={(dates) => {
                table.setFilter("from", dates?.[0] ? dates[0].startOf("day").toISOString() : null);
                table.setFilter("to", dates?.[1] ? dates[1].endOf("day").toISOString() : null);
              }}
            />
          </Flex>
        }
      />
    </>
  );
}

function ActivityChanges({ activity }: { activity: Activity }) {
  const t = useTranslations("activity");
  const attributes = (activity.changes?.attributes ?? {}) as Record<string, unknown>;
  const old = (activity.changes?.old ?? {}) as Record<string, unknown>;
  const fields = Array.from(new Set([...Object.keys(attributes), ...Object.keys(old)]));
  const show = (value: unknown) => (value === undefined || value === null || value === "" ? "—" : typeof value === "object" ? JSON.stringify(value) : String(value));

  if (fields.length === 0) {
    return activity.properties ? (
      <Descriptions size="small" column={1} items={Object.entries(activity.properties).map(([key, value]) => ({ key, label: key, children: <span className="ltr">{show(value)}</span> }))} />
    ) : null;
  }

  return (
    <Table
      size="small"
      pagination={false}
      rowKey="field"
      dataSource={fields.map((field) => ({ field, old: show(old[field]), new: show(attributes[field]) }))}
      columns={[
        { title: t("field"), dataIndex: "field", render: (v: string) => <Typography.Text code>{v}</Typography.Text> },
        { title: t("old"), dataIndex: "old" },
        { title: t("new"), dataIndex: "new" },
      ]}
    />
  );
}
