"use client";

import { RightOutlined } from "@ant-design/icons";
import { Alert, App, Button, Card, Checkbox, Descriptions, Divider, Empty, Flex, Input, InputNumber, Radio, Skeleton, Tag, Typography } from "antd";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useTranslations } from "next-intl";
import { Fragment, useMemo, useRef, useState } from "react";
import { isApiError } from "@/shared/api/client";
import { errorMessage } from "@/shared/api/formErrors";
import { dayjs, formatDate } from "@/shared/lib/date";
import { formatNumber, inputNumberFormatter } from "@/shared/lib/digits";
import { formatMoney, parseAmount } from "@/shared/lib/money";
import { useAddresses, useCustomerSlots, useOrderQuote, usePlaceOrder, usePortalCatalog, usePortalSettings } from "../api";
import { byDate, isServed, linesFrom, newIdempotencyKey, windowLabel, type Quantities } from "../onlineOrder";

const WEEKDAY_KEYS = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"] as const;

function Section({ step, title, children }: { step: number; title: string; children: React.ReactNode }) {
  return (
    <Card size="small" title={`${formatNumber(step)}. ${title}`} styles={{ title: { fontSize: 15 } }}>
      {children}
    </Card>
  );
}

/**
 * A customer orders a pickup: the address (only served ones), a window,
 * the items or "the driver lists them", and a priced summary.
 */
export function NewOrder() {
  const t = useTranslations("portal.newOrder");
  const ta = useTranslations("portal.addresses");
  const tm = useTranslations("tracking");
  const tw = useTranslations("logistics.weekdays");
  const units = useTranslations("catalog.units");
  const { message } = App.useApp();
  const router = useRouter();
  const unit = usePortalSettings().data?.currency_unit ?? "toman";
  const money = (rial: number) => `${formatMoney(rial, unit)} ${tm(unit)}`;

  const addresses = useAddresses();
  const served = useMemo(() => (addresses.data ?? []).filter(isServed), [addresses.data]);
  const [chosenAddress, setChosenAddress] = useState<number | null>(null);
  const addressId = chosenAddress ?? served.find((address) => address.is_default)?.id ?? served[0]?.id ?? null;

  const slots = useCustomerSlots(addressId);
  const [slotId, setSlotId] = useState<number | null>(null);
  const slotStillOffered = slots.data?.slots.some((slot) => slot.id === slotId) ?? false;

  const catalog = usePortalCatalog();
  const [byDriver, setByDriver] = useState(false);
  const [quantities, setQuantities] = useState<Quantities>({});
  const [notes, setNotes] = useState("");
  const lines = linesFrom(quantities);

  const quoteInput = addressId !== null && (byDriver || lines.length > 0) ? { address_id: addressId, items_by_driver: byDriver, ...(byDriver ? {} : { lines }) } : null;
  const quote = useOrderQuote(quoteInput);

  const place = usePlaceOrder();
  const key = useRef(newIdempotencyKey());

  const submit = () => {
    if (quoteInput === null || slotId === null) {
      return;
    }
    place.mutate(
      { input: { ...quoteInput, slot_id: slotId, notes: notes.trim() || null }, key: key.current },
      {
        onSuccess: (order) => {
          message.success(t("placed", { ticket: order.ticket_number }));
          router.push(`/my/orders/${order.public_id}`);
        },
        onError: (error) => {
          message.error(errorMessage(error, t("failed")));
          if (isApiError(error, 409)) {
            // The window filled up meanwhile: pick another one.
            setSlotId(null);
            void slots.refetch();
          }
        },
      },
    );
  };

  if (addresses.isLoading) {
    return <Skeleton active />;
  }

  const back = (
    <Link href="/my">
      <RightOutlined aria-hidden /> {t("back")}
    </Link>
  );

  if (served.length === 0) {
    return (
      <Flex vertical gap={16}>
        {back}
        <Empty description={t("noAddress")}>
          <Link href="/my/addresses">
            <Button type="primary">{ta("add")}</Button>
          </Link>
        </Empty>
      </Flex>
    );
  }

  const ready = quoteInput !== null && slotId !== null && slotStillOffered && quote.data !== undefined && !quote.data.below_minimum && !quote.isFetching;

  return (
    <Flex vertical gap={16}>
      {back}
      <Typography.Title level={4} style={{ margin: 0 }}>
        {t("title")}
      </Typography.Title>

      <Section step={1} title={t("address")}>
        <Radio.Group
          value={addressId}
          onChange={(event) => {
            setChosenAddress(event.target.value as number);
            setSlotId(null);
          }}
          style={{ width: "100%" }}
          aria-label={t("address")}
        >
          <Flex vertical gap={8}>
            {(addresses.data ?? []).map((address) => (
              <Radio key={address.id} value={address.id} disabled={!isServed(address)}>
                <Flex vertical>
                  <Typography.Text strong>{address.title || address.address}</Typography.Text>
                  {address.title && <Typography.Text type="secondary">{address.address}</Typography.Text>}
                  {!isServed(address) && <Typography.Text type="warning">{t("notServed")}</Typography.Text>}
                </Flex>
              </Radio>
            ))}
          </Flex>
        </Radio.Group>
        <Divider style={{ marginBlock: 12 }} />
        <Link href="/my/addresses">{t("manageAddresses")}</Link>
      </Section>

      <Section step={2} title={t("window")}>
        {slots.isLoading ? (
          <Skeleton active paragraph={{ rows: 2 }} />
        ) : slots.isError ? (
          <Alert type="error" showIcon title={errorMessage(slots.error, t("windowsError"))} />
        ) : slots.data?.slots.length === 0 ? (
          <Alert type="info" showIcon title={t("noWindows")} />
        ) : (
          <Radio.Group value={slotStillOffered ? slotId : null} onChange={(event) => setSlotId(event.target.value as number)} style={{ width: "100%" }}>
            <Flex vertical gap={12}>
              {byDate(slots.data?.slots ?? []).map((day) => (
                <Flex key={day.date} vertical gap={6} role="group" aria-label={`${tw(WEEKDAY_KEYS[dayjs(day.date).day()])} ${formatDate(day.date)}`}>
                  <Typography.Text type="secondary">
                    {tw(WEEKDAY_KEYS[dayjs(day.date).day()])} {formatDate(day.date)}
                  </Typography.Text>
                  <Flex gap={8} wrap>
                    {day.slots.map((slot) => (
                      <Radio.Button key={slot.id} value={slot.id} aria-label={`${tw(WEEKDAY_KEYS[dayjs(day.date).day()])} ${windowLabel(slot.starts_at, slot.ends_at)}`}>
                        <span className="ltr">{windowLabel(slot.starts_at, slot.ends_at)}</span>
                      </Radio.Button>
                    ))}
                  </Flex>
                </Flex>
              ))}
            </Flex>
          </Radio.Group>
        )}
      </Section>

      <Section step={3} title={t("items")}>
        <Checkbox checked={byDriver} onChange={(event) => setByDriver(event.target.checked)}>
          {t("byDriver")}
        </Checkbox>
        <Typography.Paragraph type="secondary" style={{ marginBlock: "4px 0", fontSize: 13 }}>
          {t("byDriverHelp")}
        </Typography.Paragraph>
        {!byDriver && (
          <Fragment>
            <Divider style={{ marginBlock: 12 }} />
            {catalog.isLoading ? (
              <Skeleton active />
            ) : (
              <Flex vertical gap={16}>
                {catalog.data?.categories.map((category) => (
                  <Flex key={category.id} vertical gap={8}>
                    <Typography.Text strong>{category.name}</Typography.Text>
                    {category.items.flatMap((item) =>
                      item.prices.map((price) => {
                        const lineKey = `${item.id}:${price.service_type_id}`;
                        const label = item.prices.length > 1 ? `${item.name} — ${price.service_name}` : item.name;
                        return (
                          <Flex key={lineKey} justify="space-between" align="center" gap={12}>
                            <Flex vertical style={{ minWidth: 0 }}>
                              <Typography.Text>{label}</Typography.Text>
                              <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                                {price.service_name}، {money(price.price)} / {units(item.unit)}
                              </Typography.Text>
                            </Flex>
                            <InputNumber<number>
                              aria-label={label}
                              min={0}
                              max={1000}
                              step={item.unit === "piece" ? 1 : 0.5}
                              precision={item.unit === "piece" ? 0 : 1}
                              value={quantities[lineKey] ?? 0}
                              formatter={inputNumberFormatter}
                              parser={(text) => parseAmount(text) ?? 0}
                              onChange={(value) => setQuantities((current) => ({ ...current, [lineKey]: value ?? 0 }))}
                              style={{ width: 96, flexShrink: 0 }}
                            />
                          </Flex>
                        );
                      }),
                    )}
                  </Flex>
                ))}
              </Flex>
            )}
          </Fragment>
        )}
      </Section>

      <Section step={4} title={t("summary")}>
        {quoteInput === null ? (
          <Typography.Text type="secondary">{t("pickItems")}</Typography.Text>
        ) : quote.isError ? (
          <Alert type="error" showIcon title={errorMessage(quote.error, t("quoteError"))} />
        ) : !quote.data ? (
          <Skeleton active paragraph={{ rows: 3 }} />
        ) : (
          <Flex vertical gap={12}>
            {quote.data.lines.length > 0 && (
              <Descriptions column={1} size="small">
                {quote.data.lines.map((line, index) => (
                  <Descriptions.Item key={index} label={`${line.item_name} × ${formatNumber(Number(line.quantity))}`}>
                    {money(line.line_total)}
                  </Descriptions.Item>
                ))}
              </Descriptions>
            )}
            <Descriptions column={1} size="small" aria-label={t("summary")}>
              {!quote.data.items_by_driver && <Descriptions.Item label={t("itemsTotal")}>{money(quote.data.items_total)}</Descriptions.Item>}
              <Descriptions.Item label={t("deliveryFee")}>{quote.data.delivery_fee > 0 ? money(quote.data.delivery_fee) : <Tag color="green">{t("free")}</Tag>}</Descriptions.Item>
              <Descriptions.Item label={t("total")}>
                <Typography.Text strong>{money(quote.data.total)}</Typography.Text>
              </Descriptions.Item>
            </Descriptions>
            {quote.data.items_by_driver && <Alert type="info" showIcon title={t("byDriverTotal")} />}
            {quote.data.below_minimum && quote.data.min_order !== null && (
              <Alert type="warning" showIcon title={t("belowMinimum", { amount: money(quote.data.min_order) })} />
            )}
            {!quote.data.below_minimum && quote.data.delivery_fee > 0 && quote.data.zone.free_delivery_over !== null && !quote.data.items_by_driver && (
              <Typography.Text type="secondary" style={{ fontSize: 13 }}>
                {ta("freeOver", { amount: money(quote.data.zone.free_delivery_over) })}
              </Typography.Text>
            )}
          </Flex>
        )}
        <Divider style={{ marginBlock: 12 }} />
        <Input.TextArea
          aria-label={t("notes")}
          placeholder={t("notesPlaceholder")}
          value={notes}
          onChange={(event) => setNotes(event.target.value)}
          maxLength={500}
          autoSize={{ minRows: 2, maxRows: 4 }}
        />
        {slotId === null && quoteInput !== null && (
          <Typography.Paragraph type="secondary" style={{ marginBlock: "8px 0", fontSize: 13 }}>
            {t("pickWindow")}
          </Typography.Paragraph>
        )}
        <Button type="primary" size="large" block style={{ marginTop: 12 }} disabled={!ready} loading={place.isPending} onClick={submit}>
          {t("submit")}
        </Button>
        <Typography.Paragraph type="secondary" style={{ marginBlock: "8px 0 0", fontSize: 13 }}>
          {t("payLater")}
        </Typography.Paragraph>
      </Section>
    </Flex>
  );
}
