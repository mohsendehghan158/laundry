"use client";

import { RedoOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Flex, Input, Row, Segmented, Skeleton, Switch, Tabs, Tag, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { parseAsStringLiteral, useQueryState } from "nuqs";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { NotificationLog, NotificationTemplate } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { useListParams } from "@/shared/hooks/useListParams";
import { formatDateTime } from "@/shared/lib/date";
import { DataTable } from "@/shared/ui/DataTable";
import { PageHeader } from "@/shared/ui/PageHeader";
import { previewMessage, useNotificationLogs, useNotificationTemplates, useRetryNotification, useSaveTemplate } from "../api";

const STATUSES = ["queued", "sent", "failed"] as const;
const TABS = ["log", "templates"] as const;

function TemplateCard({ template, placeholders }: { template: NotificationTemplate; placeholders: string[] }) {
  const t = useTranslations("notifications");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const save = useSaveTemplate();
  const [body, setBody] = useState(template.body);
  const [active, setActive] = useState(template.is_active);
  const dirty = body !== template.body || active !== template.is_active;

  const sample: Record<string, string> = {
    customer: t("sample.customer"),
    ticket: "T1-00042",
    total: "۴۵۰,۰۰۰",
    balance: "۰",
    due: "۱۴۰۵/۰۹/۱۰ ۱۸:۰۰",
    link: "https://example.com/track/…",
    organization: t("sample.organization"),
    branch: t("sample.branch"),
    rack: "A-12",
  };

  return (
    <Card
      title={t(`events.${template.event}`)}
      extra={<Switch checked={active} onChange={setActive} aria-label={t("active")} checkedChildren={t("on")} unCheckedChildren={t("off")} />}
    >
      <Row gutter={16}>
        <Col xs={24} md={14}>
          <Input.TextArea
            value={body}
            onChange={(e) => setBody(e.target.value)}
            autoSize={{ minRows: 4, maxRows: 8 }}
            maxLength={500}
            showCount
            disabled={!active}
            aria-label={t(`events.${template.event}`)}
          />
          <Flex gap={4} wrap style={{ marginTop: 8 }}>
            {placeholders.map((key) => (
              <Tooltip key={key} title={t(`placeholders.${key}` as never)}>
                <Tag style={{ cursor: "pointer" }} className="ltr" onClick={() => active && setBody(`${body}{${key}}`)}>
                  {`{${key}}`}
                </Tag>
              </Tooltip>
            ))}
          </Flex>
        </Col>
        <Col xs={24} md={10}>
          <Typography.Text type="secondary">{t("preview")}</Typography.Text>
          <div style={{ whiteSpace: "pre-line", background: "rgba(0,0,0,.03)", borderRadius: 8, padding: 12, marginTop: 4, minHeight: 96 }}>
            {previewMessage(body, sample)}
          </div>
        </Col>
      </Row>
      <Flex justify="end" gap={8} style={{ marginTop: 12 }}>
        {body !== template.default_body && (
          <Button onClick={() => setBody(template.default_body)} disabled={!active}>
            {t("restoreDefault")}
          </Button>
        )}
        <Button
          type="primary"
          disabled={!dirty || body.trim() === ""}
          loading={save.isPending}
          onClick={() =>
            save.mutate(
              { event: template.event, body, is_active: active },
              { onSuccess: () => message.success(tc("saved")), onError: (error) => message.error(errorMessage(error, tc("error"))) },
            )
          }
        >
          {tc("save")}
        </Button>
      </Flex>
    </Card>
  );
}

export function NotificationsPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const [tab, setTab] = useQueryState("tab", parseAsStringLiteral(TABS).withDefault("log"));
  const [params, setParams, setFilter] = useListParams({ status: parseAsStringLiteral(STATUSES) });
  const logs = useNotificationLogs({ status: params.status, page: params.page, per_page: params.per_page }, can("notifications.view"));
  const templates = useNotificationTemplates(can("notifications.manage"));
  const retry = useRetryNotification();

  const columns: ColumnsType<NotificationLog> = [
    { key: "time", dataIndex: "created_at", title: t("notifications.columns.time"), render: (v: string | null) => formatDateTime(v) },
    { key: "event", dataIndex: "event", title: t("notifications.columns.event"), render: (v: NotificationLog["event"]) => t(`notifications.events.${v}`) },
    {
      key: "order",
      dataIndex: "order",
      title: t("orders.columns.ticket"),
      render: (o: NotificationLog["order"]) => o && <Link href={`/orders/${o.id}`} className="ltr">{o.ticket_number}</Link>,
    },
    { key: "recipient", dataIndex: "recipient", title: t("notifications.columns.recipient"), render: (v: string) => <span className="ltr">{v}</span> },
    {
      key: "body",
      dataIndex: "body",
      title: t("notifications.columns.body"),
      render: (v: string) => (
        <Typography.Paragraph ellipsis={{ rows: 2, expandable: true, symbol: t("notifications.more") }} style={{ margin: 0, maxWidth: 360, whiteSpace: "pre-line" }}>
          {v}
        </Typography.Paragraph>
      ),
    },
    {
      key: "status",
      dataIndex: "status",
      title: t("common.status"),
      render: (v: NotificationLog["status"], log) => (
        <Tooltip title={log.error}>
          <Tag color={v === "sent" ? "green" : v === "failed" ? "red" : "blue"}>{t(`notifications.statuses.${v}`)}</Tag>
        </Tooltip>
      ),
    },
  ];

  if (can("notifications.manage")) {
    columns.push({
      key: "actions",
      width: 60,
      render: (_v, log) =>
        log.status === "failed" && (
          <Tooltip title={t("notifications.retry")}>
            <Button
              type="text"
              icon={<RedoOutlined />}
              aria-label={t("notifications.retry")}
              onClick={() =>
                retry.mutate(log.id, {
                  onSuccess: (updated) => (updated.status === "sent" ? message.success(t("notifications.resent")) : message.warning(t("notifications.stillFailing"))),
                  onError: (error) => message.error(errorMessage(error, t("common.error"))),
                })
              }
            />
          </Tooltip>
        ),
    });
  }

  const tabs = [];
  if (can("notifications.view")) {
    tabs.push({
      key: "log",
      label: t("notifications.tabs.log"),
      children: (
        <>
          <Segmented
            value={params.status ?? "all"}
            onChange={(v) => setFilter({ status: v === "all" ? null : (v as (typeof STATUSES)[number]) })}
            options={[{ value: "all", label: t("common.all") }, ...STATUSES.map((s) => ({ value: s, label: t(`notifications.statuses.${s}`) }))]}
            style={{ marginBottom: 16 }}
          />
          <DataTable<NotificationLog> columns={columns} result={logs.data} loading={logs.isFetching} onPageChange={(page, perPage) => setParams({ page, per_page: perPage })} />
        </>
      ),
    });
  }
  if (can("notifications.manage")) {
    tabs.push({
      key: "templates",
      label: t("notifications.tabs.templates"),
      children: templates.isLoading ? (
        <Skeleton active />
      ) : (
        <Flex vertical gap={16}>
          <Typography.Paragraph type="secondary">{t("notifications.templatesHelp")}</Typography.Paragraph>
          {(templates.data?.data ?? []).map((template) => (
            <TemplateCard key={`${template.event}-${template.body}-${template.is_active}`} template={template} placeholders={templates.data?.placeholders ?? []} />
          ))}
        </Flex>
      ),
    });
  }

  return (
    <>
      <PageHeader title={t("notifications.title")} />
      <Card>
        <Tabs activeKey={tabs.some((x) => x.key === tab) ? tab : tabs[0]?.key} onChange={(key) => setTab(key as (typeof TABS)[number])} items={tabs} />
      </Card>
    </>
  );
}
