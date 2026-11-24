"use client";

import { App, Col, Form, Input, Modal, Row, Select, Switch } from "antd";
import { useTranslations } from "next-intl";
import { useEffect, useMemo } from "react";
import { useBranchOptions } from "@/features/branches/api";
import { OWNER_ROLE, useRoles } from "@/features/roles/api";
import { useRoleLabel } from "@/features/roles/useRoleLabel";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { StoreUserInput, User } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { toLatinDigits } from "@/shared/lib/digits";
import { useSaveUser } from "../api";

type Props = {
  open: boolean;
  /** The user being edited; undefined to create one. */
  user?: User;
  onClose: () => void;
};

type Values = Omit<StoreUserInput, "password"> & { password?: string | null };

export function UserFormModal({ open, user, onClose }: Props) {
  const t = useTranslations();
  const { message } = App.useApp();
  const { can, me } = usePermission();
  const roleLabel = useRoleLabel();
  const [form] = Form.useForm<Values>();
  const save = useSaveUser();
  const roles = useRoles(open && can("roles.view"));
  const branches = useBranchOptions(open && can("branches.view"));

  const isOwner = me?.roles.includes(OWNER_ROLE) ?? false;

  const roleOptions = useMemo(() => {
    // Without roles.view, fall back to the roles the user already has.
    const names = roles.data?.map((role) => role.name) ?? user?.roles ?? [];
    return names
      .filter((name) => isOwner || name !== OWNER_ROLE)
      .map((name) => ({ value: name, label: roleLabel(name) }));
  }, [roles.data, user, isOwner, roleLabel]);

  const branchOptions = useMemo(() => {
    const list = branches.data?.data ?? me?.branches ?? [];
    return list.map((branch) => ({ value: branch.id, label: `${branch.name} (${branch.code})` }));
  }, [branches.data, me]);

  useEffect(() => {
    if (!open) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      user
        ? {
            name: user.name,
            username: user.username,
            email: user.email,
            mobile: user.mobile,
            roles: user.roles,
            branch_ids: user.branches.map((branch) => branch.id),
            is_active: user.is_active,
            locale: user.locale as Values["locale"],
          }
        : { roles: [], branch_ids: [], is_active: true, locale: "fa" },
    );
  }, [open, user, form]);

  const onFinish = (values: Values) => {
    const input = {
      ...values,
      username: toLatinDigits(values.username.trim()),
      email: values.email || null,
      mobile: values.mobile ? toLatinDigits(values.mobile) : null,
    };
    if (user && !input.password) {
      delete input.password;
    }
    save.mutate(
      { id: user?.id, input: input as StoreUserInput },
      {
        onSuccess: () => {
          message.success(user ? t("common.saved") : t("common.created"));
          onClose();
        },
        onError: (error) => {
          if (!applyFormErrors(form, error)) {
            message.error(errorMessage(error, t("common.error")));
          }
        },
      },
    );
  };

  const isSelf = user?.id === me?.id;

  return (
    <Modal
      open={open}
      width={640}
      title={user ? t("users.edit") : t("users.new")}
      okText={t("common.save")}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={save.isPending}
      forceRender
    >
      <Form<Values> form={form} layout="vertical" onFinish={onFinish} name="user">
        <Row gutter={16}>
          <Col xs={24} sm={12}>
            <Form.Item name="name" label={t("users.fields.name")} rules={[{ required: true, message: t("common.required") }, { max: 100 }]}>
              <Input />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item
              name="username"
              label={t("users.fields.username")}
              tooltip={t("users.fields.usernameHelp")}
              normalize={(value: string) => toLatinDigits(value ?? "").toLowerCase()}
              rules={[
                { required: true, message: t("common.required") },
                { pattern: /^[a-z0-9._-]{3,30}$/, message: t("users.usernameInvalid") },
              ]}
            >
              <Input dir="ltr" autoComplete="off" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item
              name="mobile"
              label={t("users.fields.mobile")}
              normalize={(value: string) => toLatinDigits(value ?? "")}
              rules={[{ pattern: /^09\d{9}$/, message: t("users.mobileInvalid") }]}
            >
              <Input dir="ltr" inputMode="tel" placeholder="09121234567" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="email" label={t("users.fields.email")} rules={[{ type: "email", message: t("users.emailInvalid") }, { max: 255 }]}>
              <Input dir="ltr" type="email" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item
              name="password"
              label={t("users.fields.password")}
              extra={user ? t("users.fields.passwordEditHelp") : undefined}
              rules={user ? [] : [{ required: true, message: t("common.required") }]}
            >
              <Input.Password dir="ltr" autoComplete="new-password" />
            </Form.Item>
          </Col>
          <Col xs={24} sm={12}>
            <Form.Item name="locale" label={t("users.fields.locale")}>
              <Select
                options={[
                  { value: "fa", label: t("users.locales.fa") },
                  { value: "en", label: t("users.locales.en") },
                ]}
              />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="roles" label={t("users.fields.roles")} rules={[{ required: true, message: t("common.required") }]}>
              <Select mode="multiple" options={roleOptions} loading={roles.isLoading} optionFilterProp="label" />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="branch_ids" label={t("users.fields.branches")} extra={t("users.fields.branchesHelp")}>
              <Select mode="multiple" options={branchOptions} loading={branches.isLoading} optionFilterProp="label" />
            </Form.Item>
          </Col>
          <Col xs={24}>
            <Form.Item name="is_active" label={t("users.fields.isActive")} valuePropName="checked">
              <Switch disabled={isSelf} />
            </Form.Item>
          </Col>
        </Row>
      </Form>
    </Modal>
  );
}
