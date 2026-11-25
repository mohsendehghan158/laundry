"use client";

import { ApartmentOutlined, SafetyCertificateOutlined, TeamOutlined } from "@ant-design/icons";
import { Alert, Card, Col, List, Row, Statistic, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatDateTime, formatLongDate } from "@/shared/lib/date";
import { formatNumber } from "@/shared/lib/digits";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useActivity } from "@/features/activity/api";
import { useMe } from "@/features/auth/api";
import { useBranches } from "@/features/branches/api";
import { useRoles } from "@/features/roles/api";
import { useUsers } from "@/features/users/api";

export function DashboardPage() {
  const t = useTranslations("dashboard");
  const tb = useTranslations("branches");
  const ta = useTranslations("activity");
  const { data: me } = useMe();
  const canBranches = usePermission("branches.view");
  const canUsers = usePermission("users.view");
  const canRoles = usePermission("roles.view");
  const canActivity = usePermission("activity.view");

  const branches = useBranches({ per_page: 1, "filter[is_active]": 1 }, canBranches);
  const roles = useRoles(canRoles);
  const activity = useActivity({ per_page: 6 }, canActivity);

  if (!me) return null;

  return (
    <>
      <PageHeader title={t("welcome", { name: me.name })} subtitle={t("today", { date: formatLongDate(new Date()) })} />
      <Alert type="info" showIcon title={t("comingSoon")} style={{ marginBottom: 16 }} />
      <Row gutter={[16, 16]}>
        {canBranches ? (
          <Col xs={24} sm={8}>
            <Card>
              <Statistic title={t("branches")} prefix={<ApartmentOutlined />} value={branches.data?.meta.total ?? 0} formatter={(v) => formatNumber(Number(v))} loading={branches.isLoading} />
            </Card>
          </Col>
        ) : null}
        {canUsers ? (
          <Col xs={24} sm={8}>
            <UsersStat label={t("users")} />
          </Col>
        ) : null}
        {canRoles ? (
          <Col xs={24} sm={8}>
            <Card>
              <Statistic title={t("roles")} prefix={<SafetyCertificateOutlined />} value={roles.data?.length ?? 0} formatter={(v) => formatNumber(Number(v))} loading={roles.isLoading} />
            </Card>
          </Col>
        ) : null}
        <Col xs={24} lg={canActivity ? 10 : 24}>
          <Card title={t("yourBranches")}>
            {me.branches.length === 0 ? (
              <Typography.Text type="secondary">{t("noBranches")}</Typography.Text>
            ) : (
              <List
                dataSource={me.branches}
                renderItem={(branch) => (
                  <List.Item extra={<Tag>{tb(`types.${branch.type}`)}</Tag>}>
                    <List.Item.Meta title={branch.name} description={<span className="ltr">{branch.code}</span>} />
                  </List.Item>
                )}
              />
            )}
          </Card>
        </Col>
        {canActivity ? (
          <Col xs={24} lg={14}>
            <Card title={t("recentActivity")}>
              <List
                loading={activity.isLoading}
                dataSource={activity.data?.data ?? []}
                renderItem={(entry) => (
                  <List.Item extra={<Typography.Text type="secondary">{formatDateTime(entry.created_at)}</Typography.Text>}>
                    <List.Item.Meta
                      title={`${entry.causer?.name ?? ta("system")} — ${entry.event && ta.has(`events.${entry.event}`) ? ta(`events.${entry.event}`) : entry.event ?? ""}`}
                      description={entry.subject_label ?? (entry.log_name && ta.has(`areas.${entry.log_name}`) ? ta(`areas.${entry.log_name}`) : null)}
                    />
                  </List.Item>
                )}
              />
            </Card>
          </Col>
        ) : null}
      </Row>
    </>
  );
}

function UsersStat({ label }: { label: string }) {
  const users = useUsers({ per_page: 1 });
  return (
    <Card>
      <Statistic title={label} prefix={<TeamOutlined />} value={users.data?.meta.total ?? 0} formatter={(v) => formatNumber(Number(v))} loading={users.isLoading} />
    </Card>
  );
}
