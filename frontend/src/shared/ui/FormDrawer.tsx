"use client";

import { Button, Drawer, Flex, Form, type FormInstance } from "antd";
import { useTranslations } from "next-intl";
import type { ReactNode } from "react";

interface FormDrawerProps<T> {
  open: boolean;
  title: ReactNode;
  form: FormInstance<T>;
  submitting?: boolean;
  onClose: () => void;
  onSubmit: (values: T) => void;
  initialValues?: Partial<T>;
  children: ReactNode;
}

/** Side drawer holding a create or edit form, with save and cancel at the bottom. */
export function FormDrawer<T extends object>({
  open,
  title,
  form,
  submitting,
  onClose,
  onSubmit,
  initialValues,
  children,
}: FormDrawerProps<T>) {
  const t = useTranslations("common");
  return (
    <Drawer
      open={open}
      title={title}
      onClose={onClose}
      size={480}
      destroyOnHidden
      footer={
        <Flex gap={8} justify="flex-end">
          <Button onClick={onClose}>{t("cancel")}</Button>
          <Button type="primary" loading={submitting} onClick={() => form.submit()}>
            {t("save")}
          </Button>
        </Flex>
      }
    >
      <Form<T> form={form} layout="vertical" onFinish={onSubmit} initialValues={initialValues} preserve={false}>
        {children}
      </Form>
    </Drawer>
  );
}
