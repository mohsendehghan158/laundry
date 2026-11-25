"use client";

import { PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Form, Input, Modal, Segmented, Select, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Transfer, TransferStatus } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useCreateTransfer, useTransferDestinations, useTransfers } from "../movementApi";

export const TRANSFER_STATUS_COLOR: Record<TransferStatus, string> = { open: "blue", sent: "gold", received: "green", cancelled: "default" };

/** Transfers between the user's branches and the plant: open, in transit and done. */
export function TransfersPage() {
  const t = useTranslations();
  const router = useRouter();
  const { message } = App.useApp();
  const { me } = usePermission();
  const [status, setStatus] = useState<TransferStatus | "active">("active");
  const [creating, setCreating] = useState(false);
  const [form] = Form.useForm<{ from_branch_id: number; to_branch_id: number; note?: string }>();
  const transfers = useTransfers(status);
  const destinations = useTransferDestinations();
  const create = useCreateTransfer();

  const columns: ColumnsType<Transfer> = [
    { key: "number", dataIndex: "number", title: t("transfers.fields.number"), render: (v: string, row) => <Link href={`/transfers/${row.id}`} className="ltr">{v}</Link> },
    { key: "route", title: t("transfers.fields.route"), render: (_v, row) => t("transfers.route", { from: row.from_branch.name, to: row.to_branch.name }) },
    { key: "status", dataIndex: "status", title: t("common.status"), render: (s: TransferStatus) => <Tag color={TRANSFER_STATUS_COLOR[s]}>{t(`transfers.statuses.${s}`)}</Tag> },
    {
      key: "pieces",
      title: t("transfers.fields.pieces"),
      render: (_v, row) => (row.status === "open" ? formatNumber(row.piece_count) : `${formatNumber(row.received_count)} / ${formatNumber(row.piece_count)}`),
    },
    { key: "bags", title: t("transfers.fields.bags"), render: (_v, row) => (row.bags_sent === null ? "—" : formatNumber(row.bags_sent)) },
    { key: "sent", dataIndex: "sent_at", title: t("transfers.fields.sentAt"), render: (v: string | null) => formatDateTime(v) || "—" },
  ];

  return (
    <>
      <PageHeader
        title={t("transfers.title")}
        subtitle={t("transfers.subtitle")}
        extra={
          <Button type="primary" icon={<PlusOutlined />} onClick={() => setCreating(true)}>
            {t("transfers.new")}
          </Button>
        }
      />
      <Card styles={{ body: { padding: 0 } }} title={
        <Segmented
          value={status}
          onChange={(value) => setStatus(value as TransferStatus | "active")}
          options={(["active", "received", "cancelled"] as const).map((value) => ({ value, label: t(`transfers.filters.${value}`) }))}
        />
      }>
        <Table<Transfer>
          rowKey="id"
          columns={columns}
          dataSource={transfers.data ?? []}
          loading={transfers.isLoading}
          pagination={false}
          scroll={{ x: "max-content" }}
          onRow={(row) => ({ onDoubleClick: () => router.push(`/transfers/${row.id}`) })}
          locale={{ emptyText: t("transfers.empty") }}
        />
      </Card>
      <Modal
        open={creating}
        title={t("transfers.new")}
        okText={t("transfers.start")}
        cancelText={t("common.cancel")}
        onCancel={() => setCreating(false)}
        onOk={() => form.submit()}
        confirmLoading={create.isPending}
        destroyOnHidden
      >
        <Form
          form={form}
          layout="vertical"
          preserve={false}
          initialValues={{ from_branch_id: me?.branches[0]?.id }}
          onFinish={(values) =>
            create.mutate(values, {
              onSuccess: (transfer) => router.push(`/transfers/${transfer.id}`),
              onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
            })
          }
        >
          <Form.Item name="from_branch_id" label={t("transfers.fields.from")} rules={[{ required: true, message: t("common.required") }]}>
            <Select options={(me?.branches ?? []).map((b) => ({ value: b.id, label: b.name }))} />
          </Form.Item>
          <Form.Item name="to_branch_id" label={t("transfers.fields.to")} rules={[{ required: true, message: t("common.required") }]}>
            <Select loading={destinations.isLoading} options={(destinations.data ?? []).map((b) => ({ value: b.id, label: b.name }))} />
          </Form.Item>
          <Form.Item name="note" label={t("transfers.fields.note")}>
            <Input maxLength={500} />
          </Form.Item>
          <Typography.Text type="secondary">{t("transfers.newHelp")}</Typography.Text>
        </Form>
      </Modal>
    </>
  );
}
