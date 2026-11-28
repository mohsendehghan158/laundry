"use client";

import { BankOutlined, DownloadOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Flex, Row, Select, Table, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { ReceivablesReport } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDate } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { StatTile } from "@/shared/ui/StatTile";
import { useExportReceivables, useReceivables } from "../api";

type AgingRow = ReceivablesReport["rows"][number];
const BUCKETS = ["current", "d30", "d60", "d90", "over90"] as const;

/** What customers and companies owe, by how long it is past due. */
export function ReceivablesPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const unit = useCurrencyUnit();
  const [branch, setBranch] = useState<number | null>(null);
  const report = useReceivables(branch);
  const exporter = useExportReceivables();
  const totals = report.data?.totals;
  const money = t(`money.${unit}`);
  const amount = (v: number) => (v === 0 ? "—" : formatMoney(v, unit));

  const columns: ColumnsType<AgingRow> = [
    {
      key: "name",
      title: t("reports.aging.name"),
      render: (_v, row) => (
        <Flex vertical>
          <Link href={row.kind === "company" ? `/companies/${row.id}` : `/customers/${row.id}`}>
            {row.kind === "company" && <BankOutlined />} {row.name}
          </Link>
          {row.mobile && (
            <Typography.Text type="secondary" className="ltr" style={{ fontSize: 12 }}>
              {row.mobile}
            </Typography.Text>
          )}
        </Flex>
      ),
    },
    { key: "orders", dataIndex: "orders", title: t("reports.columns.orders"), align: "end", render: (v: number) => formatNumber(v) },
    ...BUCKETS.map((bucket) => ({
      key: bucket,
      dataIndex: bucket,
      title: `${t(`reports.aging.${bucket}`)} (${money})`,
      align: "end" as const,
      sorter: (a: AgingRow, b: AgingRow) => a[bucket] - b[bucket],
      render: (v: number) => <Typography.Text type={bucket === "over90" && v > 0 ? "danger" : undefined}>{amount(v)}</Typography.Text>,
    })),
    { key: "total", dataIndex: "total", title: `${t("reports.aging.total")} (${money})`, align: "end", sorter: (a, b) => a.total - b.total, render: (v: number) => <strong>{formatMoney(v, unit)}</strong> },
    { key: "oldest", dataIndex: "oldest_days", title: t("reports.aging.oldest"), align: "end", sorter: (a, b) => a.oldest_days - b.oldest_days, render: (v: number) => formatNumber(v) },
  ];

  return (
    <>
      <PageHeader
        title={t("reports.aging.title")}
        subtitle={report.data ? t("reports.aging.subtitle", { date: formatDate(report.data.as_of) }) : undefined}
        extra={
          <Button icon={<DownloadOutlined />} loading={exporter.isPending} disabled={!report.data} onClick={() => exporter.mutate(branch, { onError: (error) => message.error(errorMessage(error, t("common.error"))) })}>
            {t("reports.export")}
          </Button>
        }
      />
      {(me?.branches.length ?? 0) > 1 && (
        <Select allowClear placeholder={t("reports.allBranches")} aria-label={t("reports.branch")} value={branch} onChange={(v?: number) => setBranch(v ?? null)} options={me?.branches.map((b) => ({ value: b.id, label: b.name }))} style={{ width: 220, marginBottom: 16 }} />
      )}
      {report.isError ? (
        <Alert type="error" showIcon title={errorMessage(report.error, t("common.error"))} />
      ) : (
        <>
          <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.aging.total")} value={formatMoney(totals?.total ?? 0, unit)} suffix={money} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.aging.current")} value={formatMoney(totals?.current ?? 0, unit)} suffix={money} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.aging.overdue")} value={formatMoney((totals?.total ?? 0) - (totals?.current ?? 0), unit)} suffix={money} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.aging.over90")} value={formatMoney(totals?.over90 ?? 0, unit)} suffix={money} loading={report.isLoading} />
            </Col>
          </Row>
          <Card styles={{ body: { padding: 0 } }}>
            <Table<AgingRow>
              rowKey="key"
              columns={columns}
              dataSource={report.data?.rows ?? []}
              loading={report.isFetching}
              pagination={false}
              scroll={{ x: "max-content" }}
              locale={{ emptyText: t("reports.aging.empty") }}
              summary={() =>
                totals && (report.data?.rows.length ?? 0) > 0 ? (
                  <Table.Summary fixed>
                    <Table.Summary.Row>
                      <Table.Summary.Cell index={0}>
                        <strong>{t("reports.totals")}</strong>
                      </Table.Summary.Cell>
                      <Table.Summary.Cell index={1} align="end">
                        {formatNumber(totals.orders)}
                      </Table.Summary.Cell>
                      {BUCKETS.map((bucket, i) => (
                        <Table.Summary.Cell key={bucket} index={i + 2} align="end">
                          <strong>{amount(totals[bucket])}</strong>
                        </Table.Summary.Cell>
                      ))}
                      <Table.Summary.Cell index={7} align="end">
                        <strong>{formatMoney(totals.total, unit)}</strong>
                      </Table.Summary.Cell>
                      <Table.Summary.Cell index={8} />
                    </Table.Summary.Row>
                  </Table.Summary>
                ) : null
              }
            />
          </Card>
          <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>
            {t("reports.aging.note")}
          </Typography.Paragraph>
        </>
      )}
    </>
  );
}
