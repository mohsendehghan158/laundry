"use client";

import { DeleteOutlined, PlusOutlined } from "@ant-design/icons";
import { Alert, App, Button, Empty, Flex, Form, Input, InputNumber, Modal, Popconfirm, Select, Skeleton, Table, Tag, Typography } from "antd";
import type { ColumnsType } from "antd/es/table";
import { useTranslations } from "next-intl";
import { useMemo, useState } from "react";
import { errorMessage } from "@/shared/api/formErrors";
import type { Item } from "@/shared/api/types";
import { useCurrencyUnit } from "@/shared/hooks/useCurrencyUnit";
import { inputNumberFormatter } from "@/shared/lib/digits";
import { formatMoney, parseAmount } from "@/shared/lib/money";
import { MoneyInput } from "@/shared/ui/MoneyInput";
import { PriceImport } from "./PriceImport";
import { indexPrices, priceKey, useCatalogList, useCreatePriceList, useDeletePriceList, usePriceLists, usePrices, useUpdatePrices, type PriceChange } from "../api";

/**
 * The item × service price grid. Edits are kept locally and saved in one
 * request, so a whole column can be filled in quickly.
 */
export function PriceMatrix({ editable }: { editable: boolean }) {
  const t = useTranslations("catalog");
  const tc = useTranslations("common");
  const { message } = App.useApp();
  const unit = useCurrencyUnit();
  const items = useCatalogList("items");
  const categories = useCatalogList("item-categories");
  const services = useCatalogList("service-types");
  const lists = usePriceLists();
  const [listId, setListId] = useState<number | null>(null);
  const prices = usePrices(listId);
  const defaults = usePrices(null);
  const update = useUpdatePrices(listId);
  const createList = useCreatePriceList();
  const deleteList = useDeletePriceList();
  const [creating, setCreating] = useState(false);
  const [listForm] = Form.useForm<{ name: string; percent: number | null }>();
  const [edits, setEdits] = useState<Map<string, number | null>>(new Map());
  const [category, setCategory] = useState<number | null>(null);
  const contract = listId !== null;

  const saved = useMemo(() => indexPrices(prices.data), [prices.data]);
  const defaultPrices = useMemo(() => indexPrices(defaults.data), [defaults.data]);
  const activeServices = useMemo(() => (services.data ?? []).filter((service) => service.is_active), [services.data]);
  const categoryName = useMemo(() => new Map((categories.data ?? []).map((c) => [c.id, c.name])), [categories.data]);

  const rows = useMemo(() => {
    const order = new Map((categories.data ?? []).map((c, index) => [c.id, index]));
    return (items.data ?? [])
      .filter((item) => category === null || item.item_category_id === category)
      .sort((a, b) => (order.get(a.item_category_id) ?? 0) - (order.get(b.item_category_id) ?? 0) || a.sort_order - b.sort_order);
  }, [items.data, categories.data, category]);

  if (items.isLoading || services.isLoading || prices.isLoading || categories.isLoading) {
    return <Skeleton active />;
  }
  if (rows.length === 0 && category === null) {
    return <Empty description={t("matrix.noItems")} />;
  }
  if (activeServices.length === 0) {
    return <Empty description={t("matrix.noServices")} />;
  }

  const valueOf = (itemId: number, serviceId: number): number | null => {
    const key = priceKey(itemId, serviceId);
    return edits.has(key) ? (edits.get(key) ?? null) : (saved.get(key) ?? null);
  };

  const setCell = (itemId: number, serviceId: number, value: number | null) =>
    setEdits((current) => {
      const next = new Map(current);
      const key = priceKey(itemId, serviceId);
      if ((saved.get(key) ?? null) === value) {
        next.delete(key);
      } else {
        next.set(key, value);
      }
      return next;
    });

  const saveAll = () => {
    const changes: PriceChange[] = Array.from(edits, ([key, price]) => {
      const [item_id, service_type_id] = key.split(":").map(Number);
      return { item_id, service_type_id, price };
    });
    update.mutate(changes, {
      onSuccess: () => {
        setEdits(new Map());
        message.success(tc("saved"));
      },
      onError: (error) => message.error(errorMessage(error, tc("error"))),
    });
  };

  const columns: ColumnsType<Item> = [
    {
      key: "item",
      title: t("matrix.item"),
      fixed: "right",
      width: 200,
      render: (_value, item) => (
        <Flex vertical>
          <Typography.Text strong={item.is_active} type={item.is_active ? undefined : "secondary"}>
            {item.icon ? `${item.icon} ` : ""}
            {item.name}
          </Typography.Text>
          <Typography.Text type="secondary" style={{ fontSize: 12 }}>
            {categoryName.get(item.item_category_id)} · {t(`perUnit.${item.unit}`)}
          </Typography.Text>
        </Flex>
      ),
    },
    ...activeServices.map((service) => ({
      key: `service-${service.id}`,
      title: (
        <Flex vertical>
          <span>{service.name}</span>
          <Tag className="ltr" style={{ width: "fit-content" }}>
            {service.code}
          </Tag>
        </Flex>
      ),
      width: 170,
      render: (_value: unknown, item: Item) => {
        const value = valueOf(item.id, service.id);
        const changed = edits.has(priceKey(item.id, service.id));
        // In a contract list an empty cell means the default price applies.
        const fallback = contract ? (defaultPrices.get(priceKey(item.id, service.id)) ?? null) : null;
        return editable ? (
          <MoneyInput
            unit={unit}
            value={value}
            onChange={(rial) => setCell(item.id, service.id, rial)}
            status={changed ? "warning" : undefined}
            placeholder={fallback === null ? undefined : formatMoney(fallback, unit)}
            aria-label={`${item.name} – ${service.name}`}
            controls={false}
          />
        ) : (
          <span>{value === null ? (fallback === null ? "—" : <Typography.Text type="secondary">{formatMoney(fallback, unit)}</Typography.Text>) : formatMoney(value, unit)}</span>
        );
      },
    })),
  ];

  return (
    <>
      <Flex align="center" gap={8} wrap style={{ marginBottom: 12 }}>
        <Select
          aria-label={t("priceLists.list")}
          value={listId ?? 0}
          disabled={edits.size > 0}
          onChange={(value: number) => setListId(value === 0 ? null : value)}
          options={(lists.data ?? []).map((list) => ({
            value: list.is_default ? 0 : list.id,
            label: list.is_default ? t("priceLists.default") : `${list.name} (${t("priceLists.overrides", { count: list.prices_count })})`,
          }))}
          style={{ minWidth: 260 }}
        />
        {editable && (
          <Button icon={<PlusOutlined />} disabled={edits.size > 0} onClick={() => setCreating(true)}>
            {t("priceLists.new")}
          </Button>
        )}
        {editable && contract && (
          <Popconfirm
            title={t("priceLists.deleteConfirm")}
            okText={tc("delete")}
            cancelText={tc("cancel")}
            okButtonProps={{ danger: true }}
            onConfirm={() =>
              listId !== null &&
              deleteList.mutateAsync(listId).then(
                () => {
                  setListId(null);
                  message.success(tc("deleted"));
                },
                (error: unknown) => message.error(errorMessage(error, tc("error"))),
              )
            }
          >
            <Button danger icon={<DeleteOutlined />}>
              {t("priceLists.delete")}
            </Button>
          </Popconfirm>
        )}
      </Flex>
      <Alert type="info" showIcon title={contract ? t("priceLists.help") : t("matrix.help")} style={{ marginBottom: 16 }} />
      <Flex justify="space-between" align="center" gap={8} wrap style={{ marginBottom: 16 }}>
        <Select
          allowClear
          placeholder={t("matrix.allCategories")}
          value={category}
          onChange={(value) => setCategory(value ?? null)}
          options={(categories.data ?? []).map((c) => ({ value: c.id, label: c.name }))}
          style={{ width: 220 }}
        />
        <Flex gap={8} align="center" wrap>
          {!contract && <PriceImport editable={editable} blocked={edits.size > 0} />}
          {editable && (
            <>
              <Typography.Text type={edits.size > 0 ? "warning" : "secondary"}>{t("matrix.unsaved", { count: edits.size })}</Typography.Text>
              <Button disabled={edits.size === 0} onClick={() => setEdits(new Map())}>
                {t("matrix.discard")}
              </Button>
              <Button type="primary" disabled={edits.size === 0} loading={update.isPending} onClick={saveAll}>
                {tc("save")}
              </Button>
            </>
          )}
        </Flex>
      </Flex>
      <Table<Item>
        rowKey="id"
        size="small"
        columns={columns}
        dataSource={rows}
        pagination={false}
        scroll={{ x: "max-content" }}
        sticky
      />
      <Modal
        open={creating}
        title={t("priceLists.new")}
        okText={tc("save")}
        cancelText={tc("cancel")}
        confirmLoading={createList.isPending}
        onCancel={() => setCreating(false)}
        onOk={() => listForm.submit()}
        destroyOnHidden
      >
        <Form
          form={listForm}
          layout="vertical"
          preserve={false}
          onFinish={(values) =>
            createList.mutate(
              { name: values.name, percent: values.percent ?? null },
              {
                onSuccess: (list) => {
                  setCreating(false);
                  setListId(list.id);
                  message.success(tc("created"));
                },
                onError: (error) => message.error(errorMessage(error, tc("error"))),
              },
            )
          }
        >
          <Form.Item name="name" label={t("priceLists.name")} rules={[{ required: true, message: tc("required") }]}>
            <Input maxLength={100} />
          </Form.Item>
          <Form.Item name="percent" label={t("priceLists.percent")} extra={t("priceLists.percentHelp")}>
            <InputNumber<number> min={-100} max={1000} formatter={inputNumberFormatter} parser={(text) => parseAmount(text) ?? 0} suffix="٪" style={{ width: 160 }} />
          </Form.Item>
        </Form>
      </Modal>
    </>
  );
}
