"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined } from "@ant-design/icons";
import { App, Button, Card, Col, Empty, Flex, Form, Input, InputNumber, Modal, Popconfirm, Row, Select, Space, Switch, Tag, Tooltip, Typography, theme } from "antd";
import { useTranslations } from "next-intl";
import { useEffect, useMemo, useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { Rack } from "@/shared/api/types";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatNumber, inputNumberFormatter, toLatinDigits } from "@/shared/lib/digits";
import { parseAmount } from "@/shared/lib/money";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useDeleteRack, useRacks, useSaveRack, type RackInput } from "../api";

function SlotGrid({ rack }: { rack: Rack }) {
  const t = useTranslations("racks");
  const { token } = theme.useToken();
  return (
    <Flex wrap gap={6}>
      {rack.slots.map((slot) => {
        const used = slot.orders.length > 0;
        return (
          <Tooltip key={slot.id} title={used ? slot.orders.join("، ") : t("empty")}>
            <div
              data-testid="rack-slot"
              aria-label={`${slot.code}: ${used ? slot.orders.join("، ") : t("empty")}`}
              style={{
                minWidth: 56,
                padding: "6px 8px",
                borderRadius: 6,
                textAlign: "center",
                fontSize: 12,
                border: `1px solid ${used ? token.colorPrimaryBorder : token.colorBorderSecondary}`,
                background: used ? token.colorPrimaryBg : token.colorFillQuaternary,
              }}
            >
              <div className="ltr">{slot.code}</div>
              <Typography.Text type="secondary" style={{ fontSize: 11 }}>
                {used ? formatNumber(slot.orders.length) : "—"}
              </Typography.Text>
            </div>
          </Tooltip>
        );
      })}
    </Flex>
  );
}

/** Racks of finished orders in each branch, with how full every slot is. */
export function RacksPage() {
  const t = useTranslations();
  const { message } = App.useApp();
  const { me, can } = usePermission();
  const [form] = Form.useForm<RackInput>();
  const [editing, setEditing] = useState<Rack | "new" | null>(null);
  const racks = useRacks();
  const save = useSaveRack();
  const remove = useDeleteRack();
  const branches = useMemo(() => (me?.branches ?? []).filter((b) => b.type !== "plant"), [me]);
  const manage = can("production.manage");

  useEffect(() => {
    if (editing === null) return;
    form.resetFields();
    form.setFieldsValue(
      editing === "new"
        ? { branch_id: branches[0]?.id, slot_count: 20, sort_order: 0, is_active: true }
        : { name: editing.name, code: editing.code, slot_count: editing.slots.length, sort_order: editing.sort_order, is_active: editing.is_active },
    );
  }, [editing, form, branches]);

  const onFinish = (values: RackInput) => {
    const input: RackInput = editing === "new" ? { ...values, code: toLatinDigits(values.code ?? "").toUpperCase() } : { ...values, branch_id: undefined, code: undefined };
    save.mutate(
      { id: editing === "new" || editing === null ? undefined : editing.id, input },
      {
        onSuccess: () => {
          message.success(editing === "new" ? t("common.created") : t("common.saved"));
          setEditing(null);
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, t("common.error"))),
      },
    );
  };

  const byBranch = branches.map((branch) => ({ branch, racks: (racks.data ?? []).filter((rack) => rack.branch_id === branch.id) }));

  return (
    <>
      <PageHeader
        title={t("racks.title")}
        subtitle={t("racks.subtitle")}
        extra={
          manage && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
              {t("racks.new")}
            </Button>
          )
        }
      />
      {!racks.isLoading && (racks.data ?? []).length === 0 ? (
        <Empty description={t("racks.none")} />
      ) : (
        <Flex vertical gap={16}>
          {byBranch
            .filter((group) => group.racks.length > 0)
            .map(({ branch, racks: list }) => (
              <Card key={branch.id} title={branch.name} loading={racks.isLoading}>
                <Row gutter={[16, 16]}>
                  {list.map((rack) => (
                    <Col key={rack.id} xs={24}>
                      <Flex justify="space-between" align="center" style={{ marginBottom: 8 }}>
                        <Space>
                          <Typography.Text strong>{rack.name}</Typography.Text>
                          <Tag className="ltr">{rack.code}</Tag>
                          {!rack.is_active && <Tag>{t("common.inactive")}</Tag>}
                          <Typography.Text type="secondary">
                            {t("racks.usage", { used: rack.slots.filter((s) => s.orders.length > 0).length, total: rack.slots.length })}
                          </Typography.Text>
                        </Space>
                        {manage && (
                          <Space>
                            <Button type="text" icon={<EditOutlined />} aria-label={`${t("common.edit")} ${rack.name}`} onClick={() => setEditing(rack)} />
                            <Popconfirm
                              title={t("racks.deleteConfirm", { name: rack.name })}
                              okText={t("common.delete")}
                              cancelText={t("common.cancel")}
                              okButtonProps={{ danger: true }}
                              onConfirm={() =>
                                remove.mutateAsync(rack.id).then(
                                  () => message.success(t("common.deleted")),
                                  (error: unknown) => message.error(errorMessage(error, t("common.error"))),
                                )
                              }
                            >
                              <Button type="text" danger icon={<DeleteOutlined />} aria-label={`${t("common.delete")} ${rack.name}`} />
                            </Popconfirm>
                          </Space>
                        )}
                      </Flex>
                      <SlotGrid rack={rack} />
                    </Col>
                  ))}
                </Row>
              </Card>
            ))}
        </Flex>
      )}
      <Modal
        open={editing !== null}
        title={editing === "new" ? t("racks.new") : t("racks.edit")}
        okText={t("common.save")}
        cancelText={t("common.cancel")}
        onCancel={() => setEditing(null)}
        onOk={() => form.submit()}
        confirmLoading={save.isPending}
        forceRender
      >
        <Form<RackInput> form={form} layout="vertical" onFinish={onFinish} name="rack">
          <Row gutter={16}>
            {editing === "new" && (
              <Col xs={24}>
                <Form.Item name="branch_id" label={t("stations.fields.branch")} rules={[{ required: true, message: t("common.required") }]}>
                  <Select options={branches.map((b) => ({ value: b.id, label: b.name }))} />
                </Form.Item>
              </Col>
            )}
            <Col xs={24} sm={editing === "new" ? 14 : 24}>
              <Form.Item name="name" label={t("racks.fields.name")} rules={[{ required: true, message: t("common.required") }]}>
                <Input maxLength={100} />
              </Form.Item>
            </Col>
            {editing === "new" && (
              <Col xs={24} sm={10}>
                <Form.Item
                  name="code"
                  label={t("racks.fields.code")}
                  extra={t("racks.codeHelp")}
                  normalize={(v: string) => toLatinDigits(v ?? "").toUpperCase()}
                  rules={[{ required: true, message: t("common.required") }, { pattern: /^[A-Z0-9]{1,5}$/, message: t("racks.codeInvalid") }]}
                >
                  <Input dir="ltr" maxLength={5} />
                </Form.Item>
              </Col>
            )}
            <Col xs={12}>
              <Form.Item name="slot_count" label={t("racks.fields.slots")} rules={[{ required: true, message: t("common.required") }]}>
                <InputNumber<number> min={1} max={200} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="sort_order" label={t("stations.fields.order")}>
                <InputNumber<number> min={0} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} style={{ width: "100%" }} />
              </Form.Item>
            </Col>
            <Col xs={12}>
              <Form.Item name="is_active" label={t("common.active")} valuePropName="checked">
                <Switch />
              </Form.Item>
            </Col>
          </Row>
        </Form>
      </Modal>
    </>
  );
}
