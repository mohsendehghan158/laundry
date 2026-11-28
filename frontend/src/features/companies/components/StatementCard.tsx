"use client";

import { AuditOutlined, PrinterOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Flex, Popconfirm, Row, Space, Statistic, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { openInvoicePrint } from "@/features/invoices/api";
import { useRangePresets } from "@/features/reports/presets";
import { errorMessage } from "@/shared/api/formErrors";
import type { Company, CompanyStatement } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { dayjs, formatDate, jalaliMonthRange } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { openStatementPrint, useCompanyStatement, useInvoiceStatement } from "../api";

type Row = CompanyStatement["orders"][number];
const DATE = "YYYY-MM-DD";

/** The company's statement for a period, with a one-click group invoice. */
export function StatementCard({ company }: { company: Company }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const presets = useRangePresets();
  const [range, setRange] = useState<[string, string]>(() => {
    // This month so far; "last month" is one click away in the presets.
    return [jalaliMonthRange(0)[0].format(DATE), dayjs().format(DATE)];
  });
  const statement = useCompanyStatement(company.id, range[0], range[1]);
  const invoice = useInvoiceStatement();
  const data = statement.data;
  const money = (v: number) => formatMoney(v, unit);

  const columns: ColumnsType<Row> = [
    { key: "ticket", title: t("orders.columns.ticket"), render: (_v, row) => <Link href={`/orders/${row.id}`} className="ltr">{row.ticket_number}</Link> },
    { key: "date", title: t("deliveryNotes.date"), render: (_v, row) => formatDate(row.received_at) },
    { key: "department", title: t("companies.fields.department"), render: (_v, row) => row.department ?? "—" },
    { key: "notes", title: t("statements.notes"), render: (_v, row) => (row.delivery_notes.length ? toPersianDigits(row.delivery_notes.join("، ")) : "—") },
    { key: "total", title: `${t("orders.columns.total")} (${t(`money.${unit}`)})`, align: "end", render: (_v, row) => money(row.total) },
    { key: "balance", title: `${t("billing.balance")} (${t(`money.${unit}`)})`, align: "end", render: (_v, row) => <Typography.Text type={row.balance > 0 ? "danger" : undefined}>{money(row.balance)}</Typography.Text> },
    { key: "invoice", title: t("statements.invoice"), render: (_v, row) => (row.invoice ? <Tag color="green">{toPersianDigits(row.invoice.number)}</Tag> : "—") },
  ];

  const uninvoiced = data?.uninvoiced_order_ids.length ?? 0;

  return (
    <Card
      title={t("statements.title")}
      extra={
        <Space wrap>
          <JalaliRangePicker
            size="small"
            allowClear={false}
            format="YYYY/MM/DD"
            presets={presets}
            aria-label={t("statements.period")}
            value={[dayjs(range[0]), dayjs(range[1])]}
            onChange={(value) => value?.[0] && value[1] && setRange([value[0].format(DATE), value[1].format(DATE)])}
          />
          <Button size="small" icon={<PrinterOutlined />} disabled={!data} onClick={() => openStatementPrint(company.id, range[0], range[1])}>
            {t("statements.print")}
          </Button>
          {can("invoices.issue") && (
            <Popconfirm
              title={t("statements.invoiceConfirm", { count: uninvoiced })}
              okText={t("invoices.issue")}
              cancelText={t("common.cancel")}
              disabled={uninvoiced === 0}
              onConfirm={() =>
                invoice.mutateAsync({ companyId: company.id, from: range[0], to: range[1] }).then(
                  (created) => {
                    message.success(t("invoices.issued", { number: toPersianDigits(created.number) }));
                    openInvoicePrint(created.id);
                  },
                  (error: unknown) => message.error(errorMessage(error, t("common.error"))),
                )
              }
            >
              <Button size="small" type="primary" icon={<AuditOutlined />} disabled={uninvoiced === 0} loading={invoice.isPending}>
                {t("statements.groupInvoice")}
              </Button>
            </Popconfirm>
          )}
        </Space>
      }
    >
      <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
        <Col xs={12} md={6}>
          <Statistic title={t("statements.previous")} value={money(data?.previous_balance ?? 0)} suffix={t(`money.${unit}`)} loading={statement.isLoading} />
        </Col>
        <Col xs={12} md={6}>
          <Statistic title={t("statements.total", { count: data?.totals.orders ?? 0 })} value={money(data?.totals.total ?? 0)} suffix={t(`money.${unit}`)} loading={statement.isLoading} />
        </Col>
        <Col xs={12} md={6}>
          <Statistic title={t("statements.paid")} value={money(data?.totals.paid ?? 0)} suffix={t(`money.${unit}`)} loading={statement.isLoading} />
        </Col>
        <Col xs={12} md={6}>
          <Statistic title={t("statements.due")} value={money((data?.previous_balance ?? 0) + (data?.totals.balance ?? 0))} suffix={t(`money.${unit}`)} loading={statement.isLoading} />
        </Col>
      </Row>
      {(data?.departments.length ?? 0) > 0 && (
        <Flex gap={8} wrap style={{ marginBottom: 12 }}>
          {data?.departments.map((d) => (
            <Tag key={d.name ?? "-"}>
              {d.name ?? t("statements.noDepartment")}: {formatNumber(d.orders)} · {money(d.total)}
            </Tag>
          ))}
        </Flex>
      )}
      <Table<Row> rowKey="id" size="small" columns={columns} dataSource={data?.orders ?? []} loading={statement.isFetching} pagination={false} scroll={{ x: "max-content" }} locale={{ emptyText: t("statements.empty") }} />
    </Card>
  );
}
