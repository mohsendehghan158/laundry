"use client";

import { PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Flex, Input, Modal, Segmented, Space, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { Claim, ClaimStatus } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatMoney } from "@/shared/lib/money";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useClaims, useSettleClaim } from "../movementApi";
import { ClaimDialog } from "./ClaimDialog";

const STATUS_COLOR: Record<ClaimStatus, string> = { open: "orange", resolved: "green", rejected: "default" };

/** Damage and loss cases and how each was settled with the customer. */
export function ClaimsPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const [status, setStatus] = useState<ClaimStatus>("open");
  const [opening, setOpening] = useState(false);
  const [settling, setSettling] = useState<{ claim: Claim; action: "resolve" | "reject" } | null>(null);
  const [resolution, setResolution] = useState("");
  const [compensation, setCompensation] = useState<number | null>(null);
  const claims = useClaims(status);
  const settle = useSettleClaim();
  const manage = can("claims.manage");

  const columns: ColumnsType<Claim> = [
    { key: "number", dataIndex: "number", title: t("claims.fields.number"), render: (v: string) => <span className="ltr">{v}</span> },
    { key: "kind", dataIndex: "kind", title: t("claims.fields.kind"), render: (k: Claim["kind"]) => <Tag color={k === "lost" ? "red" : "volcano"}>{t(`claims.kinds.${k}`)}</Tag> },
    {
      key: "piece",
      title: t("claims.fields.piece"),
      render: (_v, c) => (
        <Flex vertical>
          <Link href={`/pieces?tag=${c.piece.tag_code}`} className="ltr">
            {c.piece.tag_code}
          </Link>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            {[c.piece.item_name, c.piece.color_name].filter(Boolean).join(" · ")}
          </Typography.Text>
        </Flex>
      ),
    },
    {
      key: "customer",
      title: t("claims.fields.customer"),
      render: (_v, c) => (
        <Flex vertical>
          <span>{c.order.customer_name}</span>
          <Typography.Text type="secondary" className="ltr" style={{ fontSize: 12 }}>
            {c.order.customer_mobile}
          </Typography.Text>
        </Flex>
      ),
    },
    { key: "description", dataIndex: "description", title: t("claims.fields.description") },
    ...(status === "open"
      ? []
      : [
          { key: "resolution", dataIndex: "resolution", title: t("claims.fields.resolution") },
          { key: "compensation", dataIndex: "compensation", title: t("claims.fields.compensation"), render: (v: number | null) => (v === null ? "—" : `${formatMoney(v, unit)} ${t(`money.${unit}`)}`) },
        ]),
    { key: "created", title: t("claims.fields.created"), render: (_v, c) => `${formatDateTime(c.created_at)} · ${c.creator_name ?? ""}` },
    { key: "status", dataIndex: "status", title: t("common.status"), render: (s: ClaimStatus) => <Tag color={STATUS_COLOR[s]}>{t(`claims.statuses.${s}`)}</Tag> },
    ...(manage && status === "open"
      ? [{
          key: "actions",
          title: t("common.actions"),
          render: (_v: unknown, claim: Claim) => (
            <Space>
              <Button size="small" type="primary" onClick={() => setSettling({ claim, action: "resolve" })}>
                {t("claims.resolve")}
              </Button>
              <Button size="small" onClick={() => setSettling({ claim, action: "reject" })}>
                {t(claim.kind === "lost" ? "claims.found" : "claims.reject")}
              </Button>
            </Space>
          ),
        }]
      : []),
  ];

  return (
    <>
      <PageHeader
        title={t("claims.title")}
        subtitle={t("claims.subtitle")}
        extra={
          manage && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setOpening(true)}>
              {t("claims.new")}
            </Button>
          )
        }
      />
      <Card
        styles={{ body: { padding: 0 } }}
        title={<Segmented value={status} onChange={(v) => setStatus(v as ClaimStatus)} options={(["open", "resolved", "rejected"] as const).map((s) => ({ value: s, label: t(`claims.statuses.${s}`) }))} />}
      >
        <Table<Claim> rowKey="id" columns={columns} dataSource={claims.data ?? []} loading={claims.isLoading} pagination={false} scroll={{ x: "max-content" }} locale={{ emptyText: t("claims.empty") }} />
      </Card>
      <ClaimDialog open={opening} onClose={() => setOpening(false)} />
      <Modal
        open={settling !== null}
        title={settling ? t(settling.action === "resolve" ? "claims.resolveTitle" : "claims.rejectTitle", { number: settling.claim.number }) : ""}
        okText={t("orders.actions.confirm")}
        cancelText={t("common.cancel")}
        okButtonProps={{ disabled: resolution.trim() === "" }}
        confirmLoading={settle.isPending}
        onCancel={() => setSettling(null)}
        afterClose={() => {
          setResolution("");
          setCompensation(null);
        }}
        onOk={() =>
          settling &&
          settle.mutate(
            { id: settling.claim.id, action: settling.action, resolution: resolution.trim(), compensation: settling.action === "resolve" ? compensation : null },
            {
              onSuccess: () => {
                message.success(t("common.saved"));
                setSettling(null);
              },
              onError: (error) => message.error(errorMessage(error, t("common.error"))),
            },
          )
        }
        destroyOnHidden
      >
        <Flex vertical gap={12}>
          <Input.TextArea rows={3} aria-label={t("claims.fields.resolution")} placeholder={t("claims.fields.resolution")} value={resolution} onChange={(e) => setResolution(e.target.value)} maxLength={1000} />
          {settling?.action === "resolve" && (
            <div>
              <Typography.Text>{t("claims.fields.compensation")}</Typography.Text>
              <MoneyInput unit={unit} value={compensation} onChange={setCompensation} aria-label={t("claims.fields.compensation")} />
            </div>
          )}
        </Flex>
      </Modal>
    </>
  );
}
