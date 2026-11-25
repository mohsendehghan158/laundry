"use client";

import { BarChartOutlined, ExclamationCircleOutlined, TableOutlined } from "@ant-design/icons";
import { Alert, Card, Col, Flex, Row, Segmented, Select, Skeleton, Table, Tag, theme } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { parseAsInteger, parseAsString, useQueryStates } from "nuqs";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { ProductionReport } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { dayjs, formatDateTime, formatJalali, jalaliMonthRange } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { BarChart } from "@/shared/ui/BarChart";
import { JalaliRangePicker } from "@/shared/ui/JalaliDatePicker";
import { PageHeader } from "@/shared/ui/PageHeader";
import { StatTile } from "@/shared/ui/StatTile";
import { useProductionReport } from "../api";
import { useRangePresets } from "../presets";

const DATE = "YYYY-MM-DD";

/** "۲۵ ساعت", or days once it is two days or more: "۴٫۵ روز". */
export function useDuration() {
  const t = useTranslations("productionReport");
  return (hours: number | null | undefined) => {
    if (hours === null || hours === undefined) return "—";
    return hours >= 48 ? t("days", { value: formatNumber(Math.round((hours / 24) * 10) / 10) }) : t("hours", { value: formatNumber(hours) });
  };
}

const percent = (part: number, whole: number) => (whole === 0 ? "—" : `${formatNumber(Math.round((part * 1000) / whole) / 10)}٪`);

/**
 * How the workshop did: cycle time from intake to ready, how many orders
 * were ready on time, how long finished orders waited, the latest ones,
 * and the work at each station and by each person.
 */
export function ProductionReportPage() {
  const t = useTranslations();
  const { token } = theme.useToken();
  const { me } = usePermission();
  const presets = useRangePresets();
  const duration = useDuration();
  const [view, setView] = useState<"chart" | "table">("chart");
  const [params, setParams] = useQueryStates({
    from: parseAsString.withDefault(jalaliMonthRange(0)[0].format(DATE)),
    to: parseAsString.withDefault(dayjs().format(DATE)),
    branch: parseAsInteger,
  });
  const report = useProductionReport({ from: params.from, to: params.to, branch_id: params.branch });
  const data = report.data;
  const summary = data?.summary;
  const jalali = (date: string, format: string) => toPersianDigits(formatJalali(dayjs(date), format));
  // Every day of a short period gets a bar, so quiet days show as gaps.
  const days = (() => {
    const found = new Map((data?.by_day ?? []).map((day) => [day.date, day]));
    const span = dayjs(params.to).diff(dayjs(params.from), "day");
    if (!data || span > 62) return data?.by_day ?? [];
    return Array.from({ length: span + 1 }, (_, i) => {
      const date = dayjs(params.from).add(i, "day").format(DATE);
      return found.get(date) ?? { date, ready: 0, on_time: 0, avg_cycle_hours: null };
    });
  })();

  return (
    <>
      <PageHeader title={t("productionReport.title")} subtitle={t("productionReport.subtitle")} />
      <Card style={{ marginBottom: 16 }}>
        <Flex wrap gap={12}>
          <JalaliRangePicker
            aria-label={t("reports.period")}
            allowClear={false}
            format="YYYY/MM/DD"
            presets={presets}
            value={[dayjs(params.from), dayjs(params.to)]}
            onChange={(range) => range?.[0] && range[1] && setParams({ from: range[0].format(DATE), to: range[1].format(DATE) })}
          />
          {(me?.branches.length ?? 0) > 1 && (
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
        </Flex>
      </Card>

      {report.isError ? (
        <Alert type="error" showIcon title={errorMessage(report.error, t("common.error"))} />
      ) : (
        <>
          <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
            <Col xs={12} lg={4}>
              <StatTile title={t("productionReport.summary.ready")} value={formatNumber(summary?.ready ?? 0)} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={4}>
              <StatTile title={t("productionReport.summary.onTime")} value={summary?.on_time_percent === null || summary === undefined ? "—" : `${formatNumber(summary.on_time_percent)}٪`} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={4}>
              <StatTile title={t("productionReport.summary.late")} value={formatNumber(summary?.late ?? 0)} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={4}>
              <StatTile title={t("productionReport.summary.avgCycle")} value={duration(summary?.avg_cycle_hours)} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={4}>
              <StatTile title={t("productionReport.summary.medianCycle")} value={duration(summary?.median_cycle_hours)} loading={report.isLoading} />
            </Col>
            <Col xs={12} lg={4}>
              <StatTile title={t("productionReport.summary.pickupWait")} value={duration(summary?.avg_pickup_wait_hours)} loading={report.isLoading} />
            </Col>
          </Row>

          <Row gutter={[16, 16]}>
            <Col xs={24} xl={14}>
              <Card
                title={t("productionReport.daily")}
                extra={
                  <Segmented<"chart" | "table">
                    size="small"
                    value={view}
                    onChange={setView}
                    options={[
                      { value: "chart", icon: <BarChartOutlined />, title: t("dashboard.trend.chart") },
                      { value: "table", icon: <TableOutlined />, title: t("dashboard.trend.table") },
                    ]}
                  />
                }
              >
                {!data ? (
                  <Skeleton active />
                ) : data.by_day.length === 0 ? (
                  <Flex justify="center" style={{ padding: 32, color: token.colorTextSecondary }}>
                    {t("productionReport.empty")}
                  </Flex>
                ) : view === "chart" ? (
                  <BarChart
                    label={t("productionReport.dailyAria")}
                    top={100}
                    tick={(v) => `${formatNumber(v)}٪`}
                    points={days.map((day) => ({
                      key: day.date,
                      label: jalali(day.date, "D"),
                      value: day.ready === 0 ? 0 : (day.on_time * 100) / day.ready,
                      description: day.ready === 0 ? t("productionReport.dayEmpty", { date: jalali(day.date, "dddd D MMMM") }) : t("productionReport.dayTooltip", {
                        date: jalali(day.date, "dddd D MMMM"),
                        onTime: day.on_time,
                        ready: day.ready,
                        cycle: duration(day.avg_cycle_hours),
                      }),
                    }))}
                  />
                ) : (
                  <Table<ProductionReport["by_day"][number]>
                    size="small"
                    rowKey="date"
                    pagination={false}
                    dataSource={data.by_day}
                    columns={[
                      { key: "date", title: t("dashboard.trend.date"), render: (_v, d) => jalali(d.date, "dddd YYYY/MM/DD") },
                      { key: "ready", dataIndex: "ready", title: t("productionReport.summary.ready"), render: (v: number) => formatNumber(v) },
                      { key: "onTime", title: t("productionReport.summary.onTime"), render: (_v, d) => percent(d.on_time, d.ready) },
                      { key: "cycle", title: t("productionReport.summary.avgCycle"), render: (_v, d) => duration(d.avg_cycle_hours) },
                    ]}
                  />
                )}
              </Card>
            </Col>
            <Col xs={24} xl={10}>
              <Card title={t("productionReport.byService")} styles={{ body: { padding: 0 } }}>
                <Table<ProductionReport["by_service"][number]>
                  size="small"
                  rowKey="service"
                  pagination={false}
                  loading={report.isLoading}
                  dataSource={data?.by_service ?? []}
                  locale={{ emptyText: t("productionReport.empty") }}
                  columns={[
                    { key: "service", dataIndex: "service", title: t("productionReport.columns.service") },
                    { key: "orders", dataIndex: "orders", title: t("productionReport.columns.orders"), render: (v: number) => formatNumber(v) },
                    { key: "onTime", title: t("productionReport.summary.onTime"), render: (_v, s) => percent(s.on_time, s.orders) },
                    { key: "cycle", title: t("productionReport.summary.avgCycle"), render: (_v, s) => duration(s.avg_cycle_hours) },
                  ]}
                />
              </Card>
            </Col>
            <Col xs={24} xl={14}>
              <Card title={t("productionReport.stations")} styles={{ body: { padding: 0 } }}>
                <Table<ProductionReport["stations"][number]>
                  size="small"
                  rowKey="id"
                  pagination={false}
                  loading={report.isLoading}
                  dataSource={data?.stations ?? []}
                  scroll={{ x: "max-content" }}
                  locale={{ emptyText: t("productionReport.noData") }}
                  columns={[
                    { key: "station", dataIndex: "station", title: t("productionReport.columns.station") },
                    ...((me?.branches.length ?? 0) > 1 ? [{ key: "branch", dataIndex: "branch", title: t("reports.branch") }] : []),
                    { key: "scans", dataIndex: "scans", title: t("productionReport.columns.scans"), render: (v: number) => formatNumber(v) },
                    { key: "pieces", dataIndex: "pieces", title: t("productionReport.columns.pieces"), render: (v: number) => formatNumber(v) },
                    {
                      key: "qc",
                      title: t("productionReport.columns.qcFail"),
                      render: (_v, s) => (s.qc_passed + s.qc_failed === 0 ? "—" : percent(s.qc_failed, s.qc_passed + s.qc_failed)),
                    },
                  ]}
                />
              </Card>
            </Col>
            <Col xs={24} xl={10}>
              <Card title={t("productionReport.operators")} styles={{ body: { padding: 0 } }}>
                <Table<ProductionReport["operators"][number]>
                  size="small"
                  rowKey="id"
                  pagination={false}
                  loading={report.isLoading}
                  dataSource={data?.operators ?? []}
                  locale={{ emptyText: t("productionReport.noData") }}
                  columns={[
                    { key: "name", dataIndex: "name", title: t("productionReport.columns.person") },
                    { key: "scans", dataIndex: "scans", title: t("productionReport.columns.scans"), render: (v: number) => formatNumber(v) },
                    { key: "pieces", dataIndex: "pieces", title: t("productionReport.columns.pieces"), render: (v: number) => formatNumber(v) },
                  ]}
                />
              </Card>
            </Col>
            <Col xs={24}>
              <Card title={t("productionReport.lateOrders")} styles={{ body: { padding: 0 } }}>
                <Table<ProductionReport["late_orders"][number]>
                  size="small"
                  rowKey="id"
                  pagination={false}
                  loading={report.isLoading}
                  dataSource={data?.late_orders ?? []}
                  scroll={{ x: "max-content" }}
                  locale={{ emptyText: t("productionReport.noLate") }}
                  columns={[
                    { key: "ticket", title: t("orders.columns.ticket"), render: (_v, o) => <Link href={`/orders/${o.id}`} className="ltr">{o.ticket_number}</Link> },
                    { key: "customer", dataIndex: "customer_name", title: t("orders.columns.customer") },
                    { key: "due", title: t("productionReport.columns.due"), render: (_v, o) => formatDateTime(o.due_at) },
                    { key: "ready", title: t("productionReport.columns.readyAt"), render: (_v, o) => formatDateTime(o.ready_at) },
                    { key: "late", title: t("productionReport.columns.lateBy"), render: (_v, o) => <Tag color="red" icon={<ExclamationCircleOutlined />}>{duration(o.hours_late)}</Tag> },
                  ]}
                />
              </Card>
            </Col>
          </Row>
        </>
      )}
    </>
  );
}
