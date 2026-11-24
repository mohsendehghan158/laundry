"use client";

import { Alert, Card, Col, Empty, List, Row, Space, Statistic, Tag, Typography } from "antd";
import Link from "next/link";
import { useTranslations } from "next-intl";
import { useUsers } from "@/features/users/api";
import { useRoleLabel } from "@/features/roles/useRoleLabel";
import { usePermission } from "@/shared/hooks/usePermission";
import { visibleNav } from "@/shared/layout/nav";
import { formatNumber } from "@/shared/lib/digits";
import { PageHeader } from "@/shared/ui/PageHeader";

function UserCount() {
  const t = useTranslations("dashboard");
  const users = useUsers({ per_page: 1 });
  return <Statistic title={t("userCount")} value={users.data?.meta.total ?? 0} loading={users.isLoading} formatter={(v) => formatNumber(Number(v))} />;
}

export function DashboardPage() {
  const t = useTranslations();
  const { can, me } = usePermission();
  const roleLabel = useRoleLabel();

  if (!me) {
    return null;
  }

  const links = visibleNav(can)
    .flatMap((group) => group.items)
    .filter((item) => item.href !== "/dashboard");

  return (
    <>
      <PageHeader title={t("dashboard.welcome", { name: me.name })} subtitle={t("dashboard.subtitle", { organization: me.organization.name })} />
      <Row gutter={[16, 16]}>
        <Col xs={12} md={6}>
          <Card>
            <Statistic title={t("dashboard.branchCount")} value={me.branches.length} formatter={(v) => formatNumber(Number(v))} />
          </Card>
        </Col>
        {can("users.view") && (
          <Col xs={12} md={6}>
            <Card>
              <UserCount />
            </Card>
          </Col>
        )}
        <Col xs={24} md={12}>
          <Card title={t("dashboard.roles")} size="small">
            <Space wrap>
              {me.roles.map((role) => (
                <Tag key={role} color="blue">
                  {roleLabel(role)}
                </Tag>
              ))}
            </Space>
          </Card>
        </Col>
        <Col xs={24} lg={12}>
          <Card title={t("dashboard.branches")}>
            {me.branches.length === 0 ? (
              <Empty description={t("dashboard.noBranches")} />
            ) : (
              <List
                dataSource={me.branches}
                renderItem={(branch) => (
                  <List.Item extra={<Tag className="ltr">{branch.code}</Tag>}>
                    <List.Item.Meta title={branch.name} description={t(`branches.types.${branch.type}`)} />
                  </List.Item>
                )}
              />
            )}
          </Card>
        </Col>
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
        <Col xs={24}>
          <Alert type="info" showIcon title={t("dashboard.upcoming")} />
        </Col>
      </Row>
    </>
  );
}
