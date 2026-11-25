"use client";

import { EditOutlined, LockOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Checkbox, Col, Drawer, Flex, Row, Space, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { errorMessage, isApiError } from "@/shared/api/errors";
import type { Role } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatNumber } from "@/shared/lib/digits";
import { PageHeader } from "@/shared/ui/PageHeader";
import { QueryState } from "@/shared/ui/QueryState";
import { useMe } from "@/features/auth/api";
import { usePermissionGroups, useRoles, useUpdateRole } from "./api";
import { usePermissionLabel, useRoleLabel } from "./labels";

export function RolesPage() {
  const t = useTranslations("roles");
  const tc = useTranslations("common");
  const roleLabel = useRoleLabel();
  const labels = usePermissionLabel();
  const roles = useRoles();
  const groups = usePermissionGroups();
  const me = useMe();
  const canUpdate = usePermission("roles.update");
  const [editing, setEditing] = useState<Role | null>(null);

  return (
    <>
      <PageHeader title={t("title")} />
      <QueryState isLoading={roles.isLoading || groups.isLoading} error={roles.error ?? groups.error} onRetry={() => roles.refetch()}>
        <Row gutter={[16, 16]}>
          {roles.data?.map((role) => (
            <Col key={role.id} xs={24} md={12} xl={8}>
              <Card
                title={
                  <Space>
                    {role.is_owner ? <LockOutlined /> : null}
                    {roleLabel(role.name)}
                  </Space>
                }
                extra={
                  canUpdate && !role.is_owner ? (
                    <Button type="text" icon={<EditOutlined />} onClick={() => setEditing(role)}>
                      {t("edit")}
                    </Button>
                  ) : null
                }
                style={{ height: "100%" }}
              >
                <Typography.Text type="secondary">{t("usersCount", { count: formatNumber(role.users_count ?? 0) })}</Typography.Text>
                <div style={{ marginTop: 12 }}>
                  {role.is_owner ? (
                    <Typography.Text>{t("ownerNote")}</Typography.Text>
                  ) : role.permissions.length === 0 ? (
                    <Typography.Text type="secondary">—</Typography.Text>
                  ) : (
                    <Space size={[4, 8]} wrap>
                      {role.permissions.map((name) => (
                        <Tag key={name}>{labels.permission(name)}</Tag>
                      ))}
                    </Space>
                  )}
                </div>
              </Card>
            </Col>
          ))}
        </Row>
      </QueryState>
      {editing ? (
        <RoleEditor
          role={editing}
          groups={groups.data ?? []}
          held={me.data?.permissions ?? []}
          onClose={() => setEditing(null)}
          title={`${t("edit")} — ${roleLabel(editing.name)}`}
          saveLabel={tc("save")}
          cancelLabel={tc("cancel")}
        />
      ) : null}
    </>
  );
}

function RoleEditor({
  role,
  groups,
  held,
  onClose,
  title,
  saveLabel,
  cancelLabel,
}: {
  role: Role;
  groups: { group: string; permissions: string[] }[];
  held: string[];
  onClose: () => void;
  title: string;
  saveLabel: string;
  cancelLabel: string;
}) {
  const labels = usePermissionLabel();
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const update = useUpdateRole();
  const [selected, setSelected] = useState<string[]>(role.permissions);

  const save = () =>
    update.mutate(
      { id: role.id, permissions: selected },
      {
        onSuccess: () => {
          message.success(tc("saved"));
          onClose();
        },
        onError: (error) =>
          message.error(isApiError(error) && error.errors.permissions ? error.errors.permissions[0] : errorMessage(error)),
      },
    );

  return (
    <Drawer
      open
      title={title}
      onClose={onClose}
      size={480}
      footer={
        <Flex gap={8} justify="flex-end">
          <Button onClick={onClose}>{cancelLabel}</Button>
          <Button type="primary" loading={update.isPending} onClick={save}>
            {saveLabel}
          </Button>
        </Flex>
      }
    >
      {update.error && !isApiError(update.error) ? <Alert type="error" title={errorMessage(update.error)} /> : null}
      <Flex vertical gap={16}>
        {groups.map((group) => (
          <div key={group.group}>
            <Typography.Text strong>{labels.group(group.group)}</Typography.Text>
            <Flex vertical gap={6} style={{ marginTop: 8 }}>
              {group.permissions.map((name) => (
                <Checkbox
                  key={name}
                  checked={selected.includes(name)}
                  // A permission you do not hold yourself cannot be granted (the API refuses it too).
                  disabled={!held.includes(name)}
                  onChange={(event) =>
                    setSelected((current) => (event.target.checked ? [...current, name] : current.filter((p) => p !== name)))
                  }
                >
                  {labels.permission(name)}
                </Checkbox>
              ))}
            </Flex>
          </div>
        ))}
      </Flex>
    </Drawer>
  );
}
