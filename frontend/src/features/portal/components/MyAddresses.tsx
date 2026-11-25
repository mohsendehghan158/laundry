"use client";

import { AimOutlined, DeleteOutlined, EditOutlined, EnvironmentOutlined, PlusOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Checkbox, Drawer, Empty, Flex, Form, Grid, Input, Popconfirm, Skeleton, Tag, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useState } from "react";
import { applyFormErrors, errorMessage } from "@/shared/api/formErrors";
import type { PublicZone } from "@/features/logistics/types";
import { formatMoney, type CurrencyUnit } from "@/shared/lib/money";
import { PointMap } from "@/shared/map";
import { useAddresses, useDeleteAddress, usePortalSettings, useSaveAddress, useZoneCheck } from "../api";
import type { LatLng, PortalAddress } from "../types";

type Values = { title: string | null; address: string; is_default: boolean };

/** One line on what pickup and delivery cost at an address, or that there is none. */
function ZoneNote({ zone, unit }: { zone: PublicZone | null; unit: CurrencyUnit }) {
  const t = useTranslations("portal.addresses");
  const tm = useTranslations("tracking");
  const money = (rial: number) => `${formatMoney(rial, unit)} ${tm(unit)}`;

  if (zone === null) {
    return <Alert type="warning" showIcon title={t("notServed")} />;
  }
  const details = [
    zone.delivery_fee > 0 ? t("fee", { amount: money(zone.delivery_fee) }) : t("freeDelivery"),
    zone.delivery_fee > 0 && zone.free_delivery_over !== null ? t("freeOver", { amount: money(zone.free_delivery_over) }) : null,
    zone.min_order !== null ? t("minOrder", { amount: money(zone.min_order) }) : null,
  ].filter(Boolean);

  return <Alert type="success" showIcon title={t("served", { zone: zone.name })} description={details.join("، ")} />;
}

function AddressForm({ address, onDone }: { address: PortalAddress | null; onDone: () => void }) {
  const t = useTranslations("portal.addresses");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const unit = usePortalSettings().data?.currency_unit ?? "toman";
  const save = useSaveAddress();
  const [form] = Form.useForm<Values>();
  const [point, setPoint] = useState<LatLng | null>(address && address.latitude !== null && address.longitude !== null ? [address.latitude, address.longitude] : null);
  const [pointError, setPointError] = useState<string | null>(null);
  const [locating, setLocating] = useState(false);
  const check = useZoneCheck(point);

  const locate = () => {
    if (!("geolocation" in navigator)) {
      setPointError(t("locationDenied"));
      return;
    }
    setLocating(true);
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setLocating(false);
        setPointError(null);
        setPoint([Math.round(position.coords.latitude * 1e6) / 1e6, Math.round(position.coords.longitude * 1e6) / 1e6]);
      },
      () => {
        setLocating(false);
        setPointError(t("locationDenied"));
      },
      { enableHighAccuracy: true, timeout: 15_000 },
    );
  };

  const onFinish = (values: Values) => {
    if (point === null) {
      setPointError(t("pointRequired"));
      return;
    }
    save.mutate(
      { id: address?.id, input: { ...values, title: values.title?.trim() || null, latitude: point[0], longitude: point[1] } },
      {
        onSuccess: () => {
          message.success(address ? tc("saved") : tc("created"));
          onDone();
        },
        onError: (error) => !applyFormErrors(form, error) && message.error(errorMessage(error, tc("error"))),
      },
    );
  };

  return (
    <Form<Values>
      form={form}
      layout="vertical"
      requiredMark={false}
      initialValues={{ title: address?.title ?? "", address: address?.address ?? "", is_default: address?.is_default ?? false }}
      onFinish={onFinish}
    >
      <Form.Item label={t("fields.point")} extra={t("fields.pointHelp")} required>
        <Flex vertical gap={8}>
          <PointMap
            value={point}
            onChange={(next) => {
              setPoint(next);
              setPointError(null);
            }}
            height={300}
          />
          <Button icon={<AimOutlined />} loading={locating} onClick={locate} style={{ alignSelf: "flex-start" }}>
            {t("myLocation")}
          </Button>
          {pointError && <Alert type="error" showIcon title={pointError} role="alert" />}
          {point && (check.isFetching && !check.data ? <Typography.Text type="secondary">{t("checking")}</Typography.Text> : check.data && <ZoneNote zone={check.data.zone} unit={unit} />)}
        </Flex>
      </Form.Item>
      <Form.Item name="title" label={t("fields.title")} rules={[{ max: 50 }]}>
        <Input placeholder={t("fields.titlePlaceholder")} />
      </Form.Item>
      <Form.Item name="address" label={t("fields.address")} rules={[{ required: true, whitespace: true, message: tc("required") }, { max: 500 }]}>
        <Input.TextArea rows={3} placeholder={t("fields.addressPlaceholder")} autoComplete="street-address" />
      </Form.Item>
      <Form.Item name="is_default" valuePropName="checked">
        <Checkbox>{t("fields.isDefault")}</Checkbox>
      </Form.Item>
      <Button type="primary" htmlType="submit" block size="large" loading={save.isPending}>
        {tc("save")}
      </Button>
    </Form>
  );
}

/** The customer's addresses, each with its point on the map and whether it is served. */
export function MyAddresses() {
  const t = useTranslations("portal.addresses");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const screens = Grid.useBreakpoint();
  const unit = usePortalSettings().data?.currency_unit ?? "toman";
  const addresses = useAddresses();
  const save = useSaveAddress();
  const remove = useDeleteAddress();
  const [editing, setEditing] = useState<PortalAddress | "new" | null>(null);

  return (
    <Flex vertical gap={12}>
      <Flex justify="space-between" align="center" gap={8}>
        <Typography.Title level={4} style={{ margin: 0 }}>
          {t("title")}
        </Typography.Title>
        <Button type="primary" icon={<PlusOutlined />} onClick={() => setEditing("new")}>
          {t("add")}
        </Button>
      </Flex>

      {addresses.isLoading && <Skeleton active />}
      {addresses.data?.length === 0 && <Empty description={t("empty")}>{t("emptyHint")}</Empty>}
      {addresses.data?.map((address) => (
        <Card key={address.id} size="small">
          <Flex vertical gap={8}>
            <Flex justify="space-between" align="start" gap={8}>
              <Flex vertical gap={2} style={{ minWidth: 0 }}>
                <Flex align="center" gap={8} wrap>
                  <EnvironmentOutlined aria-hidden />
                  <Typography.Text strong>{address.title || t("fields.address")}</Typography.Text>
                  {address.is_default && <Tag color="blue">{t("default")}</Tag>}
                </Flex>
                <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                  {address.address}
                </Typography.Text>
              </Flex>
              <Flex style={{ flexShrink: 0 }}>
                <Button type="text" icon={<EditOutlined />} aria-label={`${tc("edit")} ${address.title ?? address.address}`} onClick={() => setEditing(address)} />
                <Popconfirm
                  title={t("deleteConfirm")}
                  okText={tc("delete")}
                  cancelText={tc("cancel")}
                  okButtonProps={{ danger: true }}
                  onConfirm={() =>
                    remove.mutateAsync(address.id).then(
                      () => message.success(tc("deleted")),
                      (error: unknown) => message.error(errorMessage(error, tc("error"))),
                    )
                  }
                >
                  <Button type="text" danger icon={<DeleteOutlined />} aria-label={`${tc("delete")} ${address.title ?? address.address}`} />
                </Popconfirm>
              </Flex>
            </Flex>
            {address.latitude === null ? <Alert type="info" showIcon title={t("pointRequired")} /> : <ZoneNote zone={address.zone} unit={unit} />}
            {!address.is_default && (
              <Button
                size="small"
                style={{ alignSelf: "flex-start" }}
                loading={save.isPending && save.variables?.id === address.id}
                onClick={() => save.mutate({ id: address.id, input: { is_default: true } }, { onError: (error) => message.error(errorMessage(error, tc("error"))) })}
              >
                {t("makeDefault")}
              </Button>
            )}
          </Flex>
        </Card>
      ))}

      <Drawer
        open={editing !== null}
        title={editing === "new" ? t("add") : t("edit")}
        onClose={() => setEditing(null)}
        placement={screens.md ? "left" : "bottom"}
        size={screens.md ? 480 : "92%"}
        destroyOnHidden
      >
        {editing !== null && <AddressForm address={editing === "new" ? null : editing} onDone={() => setEditing(null)} />}
      </Drawer>
    </Flex>
  );
}
