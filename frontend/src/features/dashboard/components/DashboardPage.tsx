"use client";

import { BarChartOutlined, ExclamationCircleOutlined, TableOutlined } from "@ant-design/icons";
import { Alert, Card, Col, Empty, Flex, List, Row, Segmented, Select, Skeleton, Space, Statistic, Table, Tag, theme, Typography } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useState, type ReactNode } from "react";
import { useRoleLabel } from "@/features/roles/useRoleLabel";
import { useDashboardReport } from "@/features/reports/api";
import { TrendChart, type TrendMeasure } from "@/features/reports/components/TrendChart";
import { errorMessage } from "@/shared/api/formErrors";
import type { DashboardReport } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { visibleNav } from "@/shared/layout/nav";
import { dayjs, formatJalali } from "@/shared/lib/date";
import { formatNumber, toPersianDigits } from "@/shared/lib/digits";
import { formatMoney } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { StatTile } from "@/shared/ui/StatTile";

type Workload = keyof DashboardReport["workload"];

/** Where each workload figure leads in the orders list. */
const WORKLOAD_LINKS: Record<Workload, string> = {
  due_today: "/orders?due=today",
  overdue: "/orders?due=overdue",
  in_process: "/orders?status=received,in_process",
  ready: "/orders?status=ready",
};

function Overview({ branchId }: { branchId: number | null }) {
  const t = useTranslations();
  const { token } = theme.useToken();
  const { can } = usePermission();
  const unit = useCurrencyUnit();
  const report = useDashboardReport(branchId);
  const [measure, setMeasure] = useState<TrendMeasure>("sales");
  const [view, setView] = useState<"chart" | "table">("chart");
  const data = report.data;
  const loading = report.isLoading;

  if (report.isError) {
    return <Alert type="error" showIcon title={errorMessage(report.error, t("common.error"))} style={{ marginBottom: 16 }} />;
  }

  const money = t(`money.${unit}`);
  const workload = (key: Workload): ReactNode => {
    const count = data?.workload[key] ?? 0;
    const late = key === "overdue" && count > 0;
    const body = (
      <Statistic
        title={t(`dashboard.workload.${key}`)}
        value={formatNumber(count)}
        loading={loading}
        prefix={late ? <ExclamationCircleOutlined /> : undefined}
        styles={{ content: late ? { color: token.colorError } : undefined }}
      />
    );
    return (
      <Col key={key} xs={12}>
        {can("orders.view") ? (
          <Link href={WORKLOAD_LINKS[key]} aria-label={`${t(`dashboard.workload.${key}`)}: ${formatNumber(count)}`}>
            <Card size="small" hoverable>
              {body}
            </Card>
          </Link>
        ) : (
          <Card size="small">{body}</Card>
        )}
      </Col>
    );
  };

  return (
    <Row gutter={[16, 16]} style={{ marginBottom: 16 }}>
      <Col xs={12} lg={6}>
        <StatTile title={t("dashboard.today.orders")} value={formatNumber(data?.today.orders ?? 0)} loading={loading} />
      </Col>
      <Col xs={12} lg={6}>
        <StatTile title={t("dashboard.today.pieces")} value={formatNumber(data?.today.pieces ?? 0)} loading={loading} />
      </Col>
      <Col xs={12} lg={6}>
        <StatTile title={t("dashboard.today.sales")} value={formatMoney(data?.today.sales ?? 0, unit)} suffix={money} loading={loading} />
      </Col>
      <Col xs={12} lg={6}>
        <StatTile title={t("dashboard.today.collected")} value={formatMoney(data?.today.collected ?? 0, unit)} suffix={money} loading={loading} />
      </Col>

      <Col xs={24} xl={16}>
        <Card
          title={t("dashboard.trend.title")}
          extra={
            <Space wrap>
              <Segmented<TrendMeasure>
                size="small"
                value={measure}
                onChange={setMeasure}
                options={[
                  { value: "sales", label: t("dashboard.trend.sales") },
                  { value: "orders", label: t("dashboard.trend.orders") },
                ]}
              />
              <Segmented<"chart" | "table">
                size="small"
                value={view}
                onChange={setView}
                options={[
                  { value: "chart", icon: <BarChartOutlined />, title: t("dashboard.trend.chart") },
                  { value: "table", icon: <TableOutlined />, title: t("dashboard.trend.table") },
                ]}
              />
            </Space>
          }
        >
          {!data ? (
            <Skeleton active />
          ) : view === "chart" ? (
            <TrendChart points={data.trend} measure={measure} unit={unit} />
          ) : (
            <Table
              size="small"
              rowKey="date"
              pagination={false}
              dataSource={[...data.trend].reverse()}
              scroll={{ y: 240 }}
              columns={[
                { key: "date", dataIndex: "date", title: t("dashboard.trend.date"), render: (d: string) => toPersianDigits(formatJalali(dayjs(d), "dddd YYYY/MM/DD")) },
                { key: "orders", dataIndex: "orders", title: t("dashboard.trend.orders"), render: (v: number) => formatNumber(v) },
                { key: "sales", dataIndex: "sales", title: `${t("dashboard.trend.sales")} (${money})`, render: (v: number) => formatMoney(v, unit) },
              ]}
            />
          )}
        </Card>
      </Col>
      <Col xs={24} xl={8}>
        <Card title={t("dashboard.workload.title")} style={{ height: "100%" }}>
          <Row gutter={[12, 12]}>{(["due_today", "overdue", "in_process", "ready"] as const).map(workload)}</Row>
        </Card>
      </Col>
    </Row>
  );
}

export function DashboardPage() {
  const t = useTranslations();
  const { can, me } = usePermission();
  const roleLabel = useRoleLabel();
  const [branchId, setBranchId] = useState<number | null>(null);

  if (!me) {
    return null;
  }

  const reports = can("reports.view");
  const links = visibleNav(can)
    .flatMap((group) => group.items)
    .filter((item) => item.href !== "/dashboard");

  return (
    <>
      <PageHeader
        title={t("dashboard.welcome", { name: me.name })}
        subtitle={t("dashboard.subtitle", { organization: me.organization.name })}
        extra={
          reports &&
          me.branches.length > 1 && (
            <Select
              allowClear
              aria-label={t("reports.branch")}
              placeholder={t("reports.allBranches")}
              value={branchId}
              onChange={(value?: number) => setBranchId(value ?? null)}
              options={me.branches.map((b) => ({ value: b.id, label: b.name }))}
              style={{ width: 200 }}
            />
          )
        }
      />
      {reports && <Overview branchId={branchId} />}
      <Row gutter={[16, 16]}>
        {links.length > 0 && (
          <Col xs={24} lg={12}>
            <Card title={t("dashboard.quickLinks")}>
              <Row gutter={[12, 12]}>
                {links.map((item) => (
                  <Col key={item.href} xs={12} sm={8}>
                    <Link href={item.href}>
                      <Card hoverable size="small" style={{ textAlign: "center" }}>
                        <div style={{ fontSize: 22 }}>{item.icon}</div>
                        <Typography.Text>{t(`nav.${item.label}`)}</Typography.Text>
                      </Card>
                    </Link>
                  </Col>
                ))}
              </Row>
            </Card>
          </Col>
        )}
        <Col xs={24} lg={12}>
          <Flex vertical gap={16}>
            <Card title={t("dashboard.roles")} size="small">
              <Space wrap>
                {me.roles.map((role) => (
                  <Tag key={role} color="blue">
                    {roleLabel(role)}
                  </Tag>
                ))}
              </Space>
            </Card>
            <Card title={t("dashboard.branches")} size="small">
              {me.branches.length === 0 ? (
                <Empty description={t("dashboard.noBranches")} />
              ) : (
                <List
                  size="small"
                  dataSource={me.branches}
                  renderItem={(branch) => (
                    <List.Item extra={<Tag className="ltr">{branch.code}</Tag>}>
                      <List.Item.Meta title={branch.name} description={t(`branches.types.${branch.type}`)} />
                    </List.Item>
                  )}
                />
              )}
            </Card>
          </Flex>
        </Col>
      </Row>
    </>
  );
}
