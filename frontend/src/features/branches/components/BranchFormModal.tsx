"use client";

import { App, Form, Input, Modal, Select, Switch } from "antd";
import { useTranslations } from "next-intl";
import { useEffect } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Branch, BranchType, StoreBranchInput } from "@/shared/api/types";
import { toLatinDigits } from "@/shared/lib/digits";
import { useSaveBranch } from "../api";

export const BRANCH_TYPES: BranchType[] = ["store", "plant", "hybrid"];

type Props = {
  open: boolean;
  /** The branch being edited; undefined to create one. */
  branch?: Branch;
  onClose: () => void;
};

export function BranchFormModal({ open, branch, onClose }: Props) {
  const t = useTranslations();
  const { message } = App.useApp();
  const [form] = Form.useForm<StoreBranchInput>();
  const save = useSaveBranch();

  useEffect(() => {
    if (!open) {
      return;
    }
    form.resetFields();
    form.setFieldsValue(
      branch
        ? { name: branch.name, code: branch.code, type: branch.type, phone: branch.phone, address: branch.address, is_active: branch.is_active }
        : { type: "store", is_active: true },
    );
  }, [open, branch, form]);

  const onFinish = (values: StoreBranchInput) => {
    const input: StoreBranchInput = {
      ...values,
      code: toLatinDigits(values.code).toUpperCase(),
      phone: values.phone ? toLatinDigits(values.phone) : null,
      address: values.address || null,
    };
    save.mutate(
      { id: branch?.id, input },
      {
        onSuccess: () => {
          message.success(branch ? t("common.saved") : t("common.created"));
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

  return (
    <Modal
      open={open}
      title={branch ? t("branches.edit") : t("branches.new")}
      okText={t("common.save")}
      cancelText={t("common.cancel")}
      onCancel={onClose}
      onOk={() => form.submit()}
      confirmLoading={save.isPending}
      forceRender
    >
      <Form<StoreBranchInput> form={form} layout="vertical" onFinish={onFinish} name="branch">
        <Form.Item name="name" label={t("branches.fields.name")} rules={[{ required: true, message: t("common.required") }, { max: 100 }]}>
          <Input />
        </Form.Item>
        <Form.Item
          name="code"
          label={t("branches.fields.code")}
          extra={t("branches.fields.codeHelp")}
          normalize={(value: string) => toLatinDigits(value ?? "").toUpperCase()}
          rules={[
            { required: true, message: t("common.required") },
            { pattern: /^[A-Z0-9]{1,10}$/, message: t("branches.codeInvalid") },
          ]}
        >
          <Input dir="ltr" maxLength={10} style={{ maxWidth: 160 }} />
        </Form.Item>
        <Form.Item name="type" label={t("branches.fields.type")} rules={[{ required: true, message: t("common.required") }]}>
          <Select options={BRANCH_TYPES.map((type) => ({ value: type, label: t(`branches.types.${type}`) }))} />
        </Form.Item>
        <Form.Item
          name="phone"
          label={t("branches.fields.phone")}
          rules={[
            {
              validator: (_rule, value?: string) =>
                !value || /^[0-9+\-\s]{5,20}$/.test(toLatinDigits(value)) ? Promise.resolve() : Promise.reject(new Error(t("branches.phoneInvalid"))),
            },
          ]}
        >
          <Input dir="ltr" inputMode="tel" />
        </Form.Item>
        <Form.Item name="address" label={t("branches.fields.address")} rules={[{ max: 500 }]}>
          <Input.TextArea rows={2} />
        </Form.Item>
        <Form.Item name="is_active" label={t("branches.fields.isActive")} valuePropName="checked">
          <Switch />
        </Form.Item>
      </Form>
    </Modal>
  );
}
