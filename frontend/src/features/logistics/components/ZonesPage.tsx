"use client";

import { DeleteOutlined, EditOutlined, PlusOutlined, RollbackOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Col, Empty, Flex, Form, Input, Popconfirm, Row, Select, Space, Switch, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { isApiError } from "@/shared/api/client";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { usePermission } from "@/shared/hooks/usePermission";
import { formatMoney } from "@/shared/lib/money";
import { ZoneMap, zoneColor, type MapZone } from "@/shared/map";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PageHeader } from "@/shared/ui/PageHeader";
import { useDeleteZone, useSaveZone, useZones } from "../api";
import type { LatLng, Zone } from "../types";

type Values = {
  name: string;
  branch_id: number;
  delivery_fee: number | null;
  free_delivery_over: number | null;
  min_order: number | null;
  is_active: boolean;
};

/**
 * The areas the business picks up from and delivers to: drawn on the map,
 * each served by one branch, with its delivery fee.
 */
export function ZonesPage() {
  const t = useTranslations("logistics");
  const tc = useTranslations("common");
  const tm = useTranslations("tracking");
  const { message } = App.useApp();
  const { me, can } = usePermission();
  const unit = useCurrencyUnit();
  const zones = useZones();
  const save = useSaveZone();
  const remove = useDeleteZone();
  const [form] = Form.useForm<Values>();
  const [editing, setEditing] = useState<Zone | "new" | null>(null);
  const [polygon, setPolygon] = useState<LatLng[]>([]);
  const [polygonError, setPolygonError] = useState<string | null>(null);
  const manage = can("logistics.manage");
  const branches = useMemo(() => me?.branches ?? [], [me]);

  const list = useMemo(() => zones.data ?? [], [zones.data]);
  const colorOf = useMemo(() => new Map(list.map((zone, index) => [zone.id, zoneColor(index)])), [list]);
  const mapZones: MapZone[] = list
    .filter((zone) => editing === null || editing === "new" || zone.id !== editing.id)
    .map((zone) => ({ id: zone.id, name: zone.name, polygon: zone.polygon, color: colorOf.get(zone.id)!, muted: editing !== null || !zone.is_active }));

  const start = (zone: Zone | "new") => {
    setEditing(zone);
    setPolygonError(null);
    setPolygon(zone === "new" ? [] : zone.polygon);
    form.resetFields();
    form.setFieldsValue(
      zone === "new"
        ? { branch_id: branches[0]?.id, delivery_fee: 0, free_delivery_over: null, min_order: null, is_active: true }
        : { ...zone },
    );
  };

  const stop = () => {
    setEditing(null);
    setPolygon([]);
  };

  const onFinish = (values: Values) => {
    if (polygon.length < 3) {
      setPolygonError(t("zones.needCorners"));
      return;
    }
    const input = { ...values, delivery_fee: values.delivery_fee ?? 0, polygon };
    save.mutate(
      { id: editing === "new" || editing === null ? undefined : editing.id, input },
      {
        onSuccess: () => {
          message.success(editing === "new" ? tc("created") : tc("saved"));
          stop();
        },
        onError: (error) => {
          if (!applyFormErrors(form, error)) {
            message.error(errorMessage(error, tc("error")));
          }
          // The corners are on the map, not a form field.
          setPolygonError(isApiError(error, 422) ? (error.errors.polygon?.[0] ?? null) : null);
        },
      },
    );
  };

  const money = (rial: number) => `${formatMoney(rial, unit)} ${tm(unit)}`;

  const editor = (
    <Card title={editing === "new" ? t("zones.new") : t("zones.edit")} size="small">
      <Alert type="info" showIcon title={t("zones.drawHelp")} style={{ marginBottom: 12 }} />
      {polygonError && <Alert type="error" showIcon title={polygonError} style={{ marginBottom: 12 }} role="alert" />}
      <Flex gap={8} align="center" wrap style={{ marginBottom: 12 }}>
        <Typography.Text type="secondary">{t("zones.corners", { count: polygon.length })}</Typography.Text>
        <Button size="small" icon={<RollbackOutlined />} disabled={polygon.length === 0} onClick={() => setPolygon(polygon.slice(0, -1))}>
          {t("zones.undoCorner")}
        </Button>
        <Button size="small" disabled={polygon.length === 0} onClick={() => setPolygon([])}>
          {t("zones.clearCorners")}
        </Button>
      </Flex>
      <Form<Values> form={form} layout="vertical" requiredMark={false} onFinish={onFinish}>
        <Form.Item name="name" label={t("zones.fields.name")} rules={[{ required: true, whitespace: true, message: tc("required") }, { max: 100 }]}>
          <Input />
        </Form.Item>
        <Form.Item name="branch_id" label={t("zones.fields.branch")} extra={t("zones.fields.branchHelp")} rules={[{ required: true, message: tc("required") }]}>
          <Select options={branches.map((branch) => ({ value: branch.id, label: branch.name }))} />
        </Form.Item>
        <Form.Item name="delivery_fee" label={t("zones.fields.deliveryFee")}>
          <MoneyInput unit={unit} min={0} style={{ width: "100%" }} />
        </Form.Item>
        <Form.Item name="free_delivery_over" label={t("zones.fields.freeOver")} extra={t("zones.fields.optional")}>
          <MoneyInput unit={unit} min={0} style={{ width: "100%" }} />
        </Form.Item>
        <Form.Item name="min_order" label={t("zones.fields.minOrder")} extra={t("zones.fields.optional")}>
          <MoneyInput unit={unit} min={0} style={{ width: "100%" }} />
        </Form.Item>
        <Form.Item name="is_active" label={tc("active")} valuePropName="checked">
          <Switch />
        </Form.Item>
        <Space>
          <Button type="primary" htmlType="submit" loading={save.isPending}>
            {tc("save")}
          </Button>
          <Button onClick={stop}>{tc("cancel")}</Button>
        </Space>
      </Form>
    </Card>
  );

  const zoneList = (
    <Flex vertical gap={8}>
      {list.length === 0 && !zones.isLoading && <Empty description={t("zones.empty")} />}
      {list.map((zone) => (
        <Card key={zone.id} size="small">
          <Flex justify="space-between" align="start" gap={8}>
            <Flex vertical gap={2} style={{ minWidth: 0 }}>
              <Flex align="center" gap={8} wrap>
                <span aria-hidden style={{ width: 12, height: 12, borderRadius: 3, background: colorOf.get(zone.id), flexShrink: 0 }} />
                <Typography.Text strong type={zone.is_active ? undefined : "secondary"}>
                  {zone.name}
                </Typography.Text>
                {!zone.is_active && <Tag>{tc("inactive")}</Tag>}
              </Flex>
              <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                {zone.branch_name}، {t("zones.fee")}: {zone.delivery_fee > 0 ? money(zone.delivery_fee) : t("zones.free")}
              </Typography.Text>
              {(zone.free_delivery_over !== null || zone.min_order !== null) && (
                <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                  {[zone.free_delivery_over !== null && t("zones.freeFrom", { amount: money(zone.free_delivery_over) }), zone.min_order !== null && t("zones.minFrom", { amount: money(zone.min_order) })]
                    .filter(Boolean)
                    .join("، ")}
                </Typography.Text>
              )}
            </Flex>
            {manage && (
              <Space size={0}>
                <Button type="text" icon={<EditOutlined />} aria-label={`${tc("edit")} ${zone.name}`} onClick={() => start(zone)} />
                <Popconfirm
                  title={t("zones.deleteConfirm", { name: zone.name })}
                  okText={tc("delete")}
                  cancelText={tc("cancel")}
                  okButtonProps={{ danger: true }}
                  onConfirm={() =>
                    remove.mutateAsync(zone.id).then(
                      () => message.success(tc("deleted")),
                      (error: unknown) => message.error(errorMessage(error, tc("error"))),
                    )
                  }
                >
                  <Button type="text" danger icon={<DeleteOutlined />} aria-label={`${tc("delete")} ${zone.name}`} />
                </Popconfirm>
              </Space>
            )}
          </Flex>
        </Card>
      ))}
    </Flex>
  );

  return (
    <>
      <PageHeader
        title={t("zones.title")}
        subtitle={t("zones.subtitle")}
        extra={
          manage &&
          editing === null && (
            <Button type="primary" icon={<PlusOutlined />} onClick={() => start("new")}>
              {t("zones.new")}
            </Button>
          )
        }
      />
      <Row gutter={[16, 16]}>
        <Col xs={24} lg={15}>
          <Card styles={{ body: { padding: 8 } }}>
            <ZoneMap
              zones={mapZones}
              editing={editing === null ? null : polygon}
              onEditingChange={(points) => {
                setPolygon(points);
                setPolygonError(null);
              }}
              onZoneClick={(id) => {
                const zone = list.find((z) => z.id === id);
                if (zone && manage) {
                  start(zone);
                }
              }}
              cornerHint={t("zones.cornerHint")}
              height={520}
            />
          </Card>
        </Col>
        <Col xs={24} lg={9}>
          {editing === null ? zoneList : editor}
        </Col>
      </Row>
    </>
  );
}
