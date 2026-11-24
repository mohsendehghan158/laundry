"use client";

import { App, Alert, Button, Card, Checkbox, Col, Collapse, Empty, Flex, Row, Skeleton, Space, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { Permission, PermissionGroup, Role } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { PageHeader } from "@/shared/ui/PageHeader";
import { usePermissionGroups, useRoles, useUpdateRole } from "../api";
import { useRoleLabel } from "../useRoleLabel";

function RolePermissions({ role, groups, editable }: { role: Role; groups: PermissionGroup[]; editable: boolean }) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me } = usePermission();
  const update = useUpdateRole();
  const [selected, setSelected] = useState<Permission[]>(role.permissions as Permission[]);

  const dirty = selected.length !== role.permissions.length || selected.some((name) => !role.permissions.includes(name));
  // Nobody can hand out a permission they do not hold themselves.
  const grantable = (name: string) => me?.permissions.includes(name) ?? false;

  const toggle = (name: Permission, checked: boolean) =>
    setSelected((current) => (checked ? [...current, name] : current.filter((item) => item !== name)));

  const save = () =>
    update.mutate(
      { id: role.id, permissions: selected },
      {
        onSuccess: (saved) => {
          setSelected(saved.permissions as Permission[]);
          message.success(t("common.saved"));
        },
        onError: (error) => message.error(errorMessage(error, t("common.error"))),
      },
    );

  if (role.is_owner) {
    return <Alert type="info" showIcon title={t("roles.ownerNote")} />;
  }

  return (
    <>
      <Row gutter={[24, 16]}>
        {groups.map((group) => (
          <Col key={group.group} xs={24} sm={12} lg={8}>
            <Typography.Text strong>{t.has(`roles.groups.${group.group}` as never) ? t(`roles.groups.${group.group}` as never) : group.group}</Typography.Text>
            <Flex vertical gap={4} style={{ marginTop: 8 }}>
              {group.permissions.map((name) => (
                <Checkbox
                  key={name}
                  checked={selected.includes(name as Permission)}
                  disabled={!editable || (!grantable(name) && !selected.includes(name as Permission))}
                  onChange={(event) => toggle(name as Permission, event.target.checked)}
                >
                  {t.has(`roles.permissions.${name}` as never) ? t(`roles.permissions.${name}` as never) : name}
                </Checkbox>
              ))}
            </Flex>
          </Col>
        ))}
      </Row>
      {editable && (
        <Flex justify="end" gap={8} style={{ marginTop: 16 }}>
          <Button disabled={!dirty} onClick={() => setSelected(role.permissions as Permission[])}>
            {t("common.cancel")}
          </Button>
          <Button type="primary" disabled={!dirty} loading={update.isPending} onClick={save}>
            {t("common.save")}
          </Button>
        </Flex>
      )}
    </>
  );
}

export function RolesPage() {
  const t = useTranslations();
  const { can } = usePermission();
  const roleLabel = useRoleLabel();
  const roles = useRoles();
  const groups = usePermissionGroups();
  const editable = can("roles.update");

  return (
    <>
      <PageHeader title={t("roles.title")} subtitle={t("roles.description")} />
      {!editable && <Alert type="info" showIcon title={t("roles.readOnly")} style={{ marginBottom: 16 }} />}
      <Card>
        {roles.isLoading || groups.isLoading ? (
          <Skeleton active />
        ) : !roles.data?.length || !groups.data ? (
          <Empty />
        ) : (
          <Collapse
            accordion
            items={roles.data.map((role) => ({
              key: role.id,
              label: (
                <Space>
                  <Typography.Text strong>{roleLabel(role.name)}</Typography.Text>
                  <Tag>{t("roles.usersCount", { count: role.users_count ?? 0 })}</Tag>
                </Space>
              ),
              children: <RolePermissions key={role.permissions.join()} role={role} groups={groups.data} editable={editable} />,
            }))}
          />
        )}
      </Card>
    </>
  );
}
