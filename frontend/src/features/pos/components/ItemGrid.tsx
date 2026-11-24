"use client";

import { Button, Empty, Flex, Modal, Segmented, Typography } from "antd";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { indexPrices, priceKey } from "@/features/catalog/api";
import type { CounterCatalog, Item, ServiceType } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { formatMoney } from "@/shared/lib/money";

type Props = {
  catalog: CounterCatalog;
  onAdd: (item: Item, service: ServiceType) => void;
};

/**
 * Large buttons for every item of the chosen category. An item priced for
 * one service is added straight away; otherwise the counter picks the
 * service, each button showing its price.
 */
export function ItemGrid({ catalog, onAdd }: Props) {
  const t = useTranslations();
  const unit = useCurrencyUnit();
  const [categoryId, setCategoryId] = useState<number | null>(catalog.categories[0]?.id ?? null);
  const [choosing, setChoosing] = useState<Item | null>(null);
  const prices = useMemo(() => indexPrices(catalog.prices), [catalog.prices]);

  const servicesFor = (item: Item) => catalog.service_types.filter((service) => prices.has(priceKey(item.id, service.id)));

  const category = catalog.categories.find((c) => c.id === categoryId) ?? catalog.categories[0];
  const items = (category?.items ?? []) as Item[];

  const pick = (item: Item) => {
    const services = servicesFor(item);
    if (services.length === 1) {
      onAdd(item, services[0]);
    } else if (services.length > 1) {
      setChoosing(item);
    }
  };

  if (catalog.categories.length === 0) {
    return <Empty description={t("pos.emptyCatalog")} />;
  }

  return (
    <>
      <Segmented
        block
        size="large"
        value={category?.id}
        onChange={(value) => setCategoryId(Number(value))}
        options={catalog.categories.map((c) => ({ value: c.id, label: `${c.icon ?? ""} ${c.name}`.trim() }))}
        style={{ marginBottom: 16, overflowX: "auto" }}
      />
      <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fill, minmax(128px, 1fr))", gap: 12 }}>
        {items.map((item) => {
          const services = servicesFor(item);
          return (
            <Button
              key={item.id}
              onClick={() => pick(item)}
              disabled={services.length === 0}
              style={{ height: 104, whiteSpace: "normal", display: "flex", flexDirection: "column", gap: 4, padding: 8 }}
              aria-label={item.name}
            >
              <span style={{ fontSize: 30, lineHeight: 1 }}>{item.icon ?? category?.icon ?? "🧺"}</span>
              <Typography.Text strong style={{ fontSize: 15 }}>
                {item.name}
              </Typography.Text>
              {services.length === 0 ? (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                  {t("pos.noPrice")}
                </Typography.Text>
              ) : (
                <Typography.Text type="secondary" style={{ fontSize: 12 }}>
                  {services.length === 1 ? services[0].name : t("pos.serviceCount", { count: services.length })}
                </Typography.Text>
              )}
            </Button>
          );
        })}
      </div>
      <Modal open={choosing !== null} title={choosing?.name} footer={null} onCancel={() => setChoosing(null)} destroyOnHidden>
        <Flex vertical gap={12}>
          {choosing &&
            servicesFor(choosing).map((service) => (
              <Button
                key={service.id}
                size="large"
                style={{ height: 64, justifyContent: "space-between", display: "flex" }}
                onClick={() => {
                  onAdd(choosing, service);
                  setChoosing(null);
                }}
              >
                <span>{service.name}</span>
                <span>
                  {formatMoney(prices.get(priceKey(choosing.id, service.id)), unit)} {t(`money.${unit}`)}{" "}
                  <Typography.Text type="secondary">/ {t(`catalog.perUnit.${choosing.unit}`)}</Typography.Text>
                </span>
              </Button>
            ))}
        </Flex>
      </Modal>
    </>
  );
}
