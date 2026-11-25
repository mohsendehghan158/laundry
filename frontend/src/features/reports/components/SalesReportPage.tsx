"use client";

import { DownloadOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Flex, Row, Segmented, Select, Table, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { parseAsInteger, parseAsString, parseAsStringLiteral, useQueryStates } from "nuqs";
import { errorMessage } from "@/shared/api/formErrors";
import type { SalesGroup, SalesRow } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { dayjs, formatJalali, jalaliMonthRange } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { PageHeader } from "@/shared/ui/PageHeader";
import { StatTile } from "@/shared/ui/StatTile";
import { useExportSales, useSalesReport, type SalesFilters } from "../api";
import { useRangePresets } from "../presets";

const GROUPS: SalesGroup[] = ["day", "service", "item", "branch", "user"];
const DATE = "YYYY-MM-DD";
/** Grouped by service or item, figures come from order lines. */
const LINE_GROUPS: SalesGroup[] = ["service", "item"];

export function SalesReportPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const unit = useCurrencyUnit();
  const [defaultFrom] = jalaliMonthRange(0);
  const [params, setParams] = useQueryStates({
    from: parseAsString.withDefault(defaultFrom.format(DATE)),
    to: parseAsString.withDefault(dayjs().format(DATE)),
    group: parseAsStringLiteral(GROUPS).withDefault("day"),
    branch: parseAsInteger,
  });
  const filters: SalesFilters = { from: params.from, to: params.to, group: params.group, branch_id: params.branch };
  const report = useSalesReport(filters);
  const exporter = useExportSales();
  const multiBranch = (me?.branches.length ?? 0) > 1;
  const lines = LINE_GROUPS.includes(params.group);
  const money = t(`money.${unit}`);
  const totals = report.data?.totals;

  const presets = useRangePresets();

  const amount = (v: number | null) => (v === null ? "" : formatMoney(v, unit));
  const columns: ColumnsType<SalesRow> = [
    {
      key: "label",
      dataIndex: "label",
      title: t(`reports.groups.${params.group}`),
      render: (label: string) => (params.group === "day" ? toPersianDigits(formatJalali(dayjs(label), "dddd YYYY/MM/DD")) : label),
    },
    {
      key: "orders",
      dataIndex: "orders",
      title: t("reports.columns.orders"),
      align: "end",
      sorter: (a, b) => (a.orders ?? 0) - (b.orders ?? 0),
      render: (v: number | null) => formatNumber(v),
    },
    ...(lines
      ? [
          {
            key: "quantity",
            dataIndex: "quantity",
            title: t("reports.columns.quantity"),
            align: "end",
            sorter: (a: SalesRow, b: SalesRow) => (a.quantity ?? 0) - (b.quantity ?? 0),
            render: (v: number | null) => formatNumber(v),
          } as const,
        ]
      : [
          { key: "discount", dataIndex: "discount", title: `${t("reports.columns.discount")} (${money})`, align: "end", render: amount } as const,
          { key: "tax", dataIndex: "tax", title: `${t("reports.columns.tax")} (${money})`, align: "end", render: amount } as const,
        ]),
    {
      key: "total",
      dataIndex: "total",
      title: `${t("reports.columns.total")} (${money})`,
      align: "end",
      sorter: (a, b) => a.total - b.total,
      render: amount,
    },
    ...(params.group !== "day"
      ? []
      : [
          {
            key: "collected",
            dataIndex: "collected",
            title: `${t("reports.columns.collected")} (${money})`,
            align: "end",
            sorter: (a: SalesRow, b: SalesRow) => (a.collected ?? 0) - (b.collected ?? 0),
            render: amount,
          } as const,
        ]),
  ];

  const onExport = () =>
    exporter.mutate(filters, {
      onError: (error) => message.error(errorMessage(error, t("common.error"))),
    });

  return (
    <>
      <PageHeader
        title={t("reports.title")}
        subtitle={t("reports.subtitle")}
        extra={
          <Button icon={<DownloadOutlined />} onClick={onExport} loading={exporter.isPending} disabled={!report.data}>
            {t("reports.export")}
          </Button>
        }
      />
      <Card style={{ marginBottom: 16 }}>
        <Flex wrap gap={12} align="center">
          <JalaliRangePicker
            aria-label={t("reports.period")}
            allowClear={false}
            format="YYYY/MM/DD"
            presets={presets}
            value={[dayjs(params.from), dayjs(params.to)]}
            onChange={(range) => range?.[0] && range[1] && setParams({ from: range[0].format(DATE), to: range[1].format(DATE) })}
          />
          {multiBranch && (
            <Select
              allowClear
              aria-label={t("reports.branch")}
              placeholder={t("reports.allBranches")}
              value={params.branch}
              onChange={(branch?: number) => setParams({ branch: branch ?? null })}
              options={me?.branches.map((b) => ({ value: b.id, label: b.name }))}
              style={{ width: 200 }}
            />
          )}
          <Segmented<SalesGroup>
            value={params.group}
            onChange={(group) => setParams({ group })}
            options={GROUPS.filter((g) => g !== "branch" || multiBranch).map((g) => ({ value: g, label: t(`reports.groups.${g}`) }))}
          />
        </Flex>
      </Card>

      {report.isError ? (
        <Alert type="error" showIcon title={errorMessage(report.error, t("common.error"))} />
      ) : (
        <>
          <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.summary.sales")} value={formatMoney(totals?.total ?? 0, unit)} suffix={money} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.summary.collected")} value={formatMoney(totals?.collected ?? 0, unit)} suffix={money} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={6}>
              <StatTile title={t("reports.summary.orders")} value={formatNumber(totals?.orders ?? 0)} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={6}>
              {lines ? (
                <StatTile title={t("reports.summary.quantity")} value={formatNumber(totals?.quantity ?? 0)} loading={report.isLoading} />
              ) : (
                <StatTile
                  title={t("reports.summary.average")}
                  value={formatMoney(totals && totals.orders > 0 ? Math.round(totals.total / totals.orders) : 0, unit)}
                  suffix={money}
                  loading={report.isLoading}
                />
              )}
            </Col>
          </Row>
          <Card styles={{ body: { padding: 0 } }}>
            <Table<SalesRow>
              rowKey="key"
              columns={columns}
              dataSource={report.data?.rows ?? []}
              loading={report.isFetching}
              pagination={false}
              scroll={{ x: "max-content" }}
              locale={{ emptyText: t("reports.empty") }}
              summary={() =>
                totals && (report.data?.rows.length ?? 0) > 0 ? (
                  <Table.Summary fixed>
                    <Table.Summary.Row>
                      {columns.map((column, index) => {
                        const key = column.key as keyof typeof totals | "label";
                        // By service or item the rows are order lines; so is their total.
                        const value = key === "label" ? null : key === "total" && lines ? totals.lines : totals[key];
                        return (
                          <Table.Summary.Cell key={String(column.key)} index={index} align={index === 0 ? undefined : "end"}>
                            <Typography.Text strong>
                              {key === "label" ? t("reports.totals") : key === "orders" || key === "quantity" ? formatNumber(value) : amount(value)}
                            </Typography.Text>
                          </Table.Summary.Cell>
                        );
                      })}
                    </Table.Summary.Row>
                  </Table.Summary>
                ) : null
              }
            />
          </Card>
          {lines && (
            <Typography.Paragraph type="secondary" style={{ marginTop: 12 }}>
              {t("reports.lineNote")}
            </Typography.Paragraph>
          )}
        </>
      )}
    </>
  );
}
