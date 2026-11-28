"use client";

import { PrinterOutlined, StopOutlined } from "@ant-design/icons";
import { App, Button, Card, Flex, Input, Modal, Segmented, Select, Space, Tag, Tooltip, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { parseAsInteger, parseAsString, parseAsStringLiteral } from "nuqs";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { Invoice, InvoiceStatus } from "@/shared/api/types";
import { useListParams } from "@/shared/hooks/useListParams";
import { usePermission } from "@/shared/hooks/usePermission";
import { dayjs, formatDateTime } from "@/shared/lib/date";
import { toPersianDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { DataTable } from "@/shared/ui/DataTable";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { PageHeader } from "@/shared/ui/PageHeader";
import { openInvoicePrint, useInvoices, useVoidInvoice } from "../api";

const STATUSES: InvoiceStatus[] = ["issued", "void"];
const DATE = "YYYY-MM-DD";

/** Asks why an invoice is voided, then voids it. */
export function VoidInvoiceModal({ invoice, onClose }: { invoice: Invoice | null; onClose: () => void }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const [reason, setReason] = useState("");
  const voidInvoice = useVoidInvoice();

  const close = () => {
    setReason("");
    onClose();
  };

  return (
    <Modal
      open={invoice !== null}
      title={t("invoices.voidTitle", { number: toPersianDigits(invoice?.number ?? "") })}
      okText={t("invoices.void")}
      cancelText={t("common.cancel")}
      okButtonProps={{ danger: true, disabled: reason.trim() === "" }}
      confirmLoading={voidInvoice.isPending}
      onCancel={close}
      onOk={() =>
        invoice &&
        voidInvoice.mutate(
          { id: invoice.id, reason: reason.trim() },
          {
            onSuccess: () => {
              message.success(t("invoices.voided"));
              close();
            },
            onError: (error) => message.error(errorMessage(error, t("common.error"))),
          },
        )
      }
      destroyOnHidden
    >
      <Typography.Paragraph>{t("invoices.voidPrompt")}</Typography.Paragraph>
      <Input autoFocus value={reason} onChange={(e) => setReason(e.target.value)} placeholder={t("invoices.voidReason")} maxLength={500} aria-label={t("invoices.voidReason")} />
    </Modal>
  );
}

export function InvoicesPage() {
  const t = useTranslations();
  const { can, me } = usePermission();
  const [voiding, setVoiding] = useState<Invoice | null>(null);
  const [params, setParams, setFilter] = useListParams({
    status: parseAsStringLiteral(STATUSES),
    branch: parseAsInteger,
    from: parseAsString,
    to: parseAsString,
  });

  const invoices = useInvoices({ q: params.q, status: params.status, branch_id: params.branch, from: params.from, to: params.to, page: params.page, per_page: params.per_page });

  const columns: ColumnsType<Invoice> = [
    { key: "number", dataIndex: "number", title: t("invoices.columns.number"), render: (v: number) => <strong>{toPersianDigits(v)}</strong> },
    { key: "issued_at", dataIndex: "issued_at", title: t("invoices.columns.issuedAt"), render: (v: string) => formatDateTime(v) },
    {
      key: "buyer",
      title: t("invoices.columns.buyer"),
      render: (_v, invoice) => (
        <Flex vertical>
          <span>{invoice.buyer.name}</span>
          <Link href={`/customers/${invoice.customer.id}`}>
            <Typography.Text type="secondary" style={{ fontSize: 12 }}>
              {invoice.customer.name} · <span className="ltr">{invoice.customer.mobile}</span>
            </Typography.Text>
          </Link>
        </Flex>
      ),
    },
    { key: "tax", dataIndex: "tax_amount", title: t("invoices.columns.tax"), render: (v: number) => formatMoney(v, "rial") },
    { key: "total", dataIndex: "total", title: t("invoices.columns.totalRial"), render: (v: number) => <strong>{formatMoney(v, "rial")}</strong> },
    {
      key: "status",
      title: t("common.status"),
      render: (_v, invoice) =>
        invoice.status === "void" ? (
          <Tooltip title={invoice.void?.reason}>
            <Tag color="red">{t("invoices.statuses.void")}</Tag>
          </Tooltip>
        ) : (
          <Tag color="green">{t("invoices.statuses.issued")}</Tag>
        ),
    },
    {
      key: "actions",
      title: t("common.actions"),
      width: 110,
      render: (_v, invoice) => (
        <Space>
          <Tooltip title={t("invoices.print")}>
            <Button type="text" icon={<PrinterOutlined />} aria-label={t("invoices.print")} onClick={() => openInvoicePrint(invoice.id)} />
          </Tooltip>
          {invoice.status === "issued" && can("invoices.void") && (
            <Tooltip title={t("invoices.void")}>
              <Button type="text" danger icon={<StopOutlined />} aria-label={t("invoices.void")} onClick={() => setVoiding(invoice)} />
            </Tooltip>
          )}
        </Space>
      ),
    },
  ];

  if ((me?.branches.length ?? 0) > 1) {
    columns.splice(3, 0, { key: "branch", dataIndex: "branch", title: t("orders.columns.branch"), render: (b: Invoice["branch"]) => b.name });
  }

  return (
    <>
      <PageHeader title={t("invoices.title")} subtitle={t("invoices.subtitle")} />
      <Card>
        <Flex gap={8} wrap style={{ marginBottom: 16 }}>
          <Input.Search allowClear defaultValue={params.q} placeholder={t("invoices.searchPlaceholder")} onSearch={(q) => setFilter({ q: q || null })} style={{ width: 280 }} />
          <Segmented
            value={params.status ?? "all"}
            onChange={(value) => setFilter({ status: value === "all" ? null : (value as InvoiceStatus) })}
            options={[{ value: "all", label: t("common.all") }, ...STATUSES.map((s) => ({ value: s, label: t(`invoices.statuses.${s}`) }))]}
          />
          <JalaliRangePicker
            allowEmpty={[true, true]}
            placeholder={[t("activity.filters.from"), t("activity.filters.to")]}
            value={[params.from ? dayjs(params.from) : null, params.to ? dayjs(params.to) : null]}
            onChange={(range) => setFilter({ from: range?.[0] ? range[0].format(DATE) : null, to: range?.[1] ? range[1].format(DATE) : null })}
            format="YYYY/MM/DD"
          />
          {(me?.branches.length ?? 0) > 1 && (
            <Select
              allowClear
              placeholder={t("orders.columns.branch")}
              value={params.branch}
              onChange={(branch) => setFilter({ branch: branch ?? null })}
              options={me?.branches.map((b) => ({ value: b.id, label: b.name }))}
              style={{ width: 180 }}
            />
          )}
        </Flex>
        <DataTable<Invoice>
          columns={columns}
          result={invoices.data}
          loading={invoices.isFetching}
          onPageChange={(page, perPage) => setParams({ page, per_page: perPage })}
        />
      </Card>
      <VoidInvoiceModal invoice={voiding} onClose={() => setVoiding(null)} />
    </>
  );
}
